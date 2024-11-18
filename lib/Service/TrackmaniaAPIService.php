<?php
/**
 * Nextcloud - Trackmania
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Trackmania\Service;

use DateTime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Controller\ConfigController;
use OCA\Trackmania\Exception\TmApiRequestException;
use OCA\Trackmania\Exception\TokenRefreshException;
use OCP\AppFramework\Http;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use Throwable;

class TrackmaniaAPIService {

	private IClient $client;
	private ICache $cache;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private IAppConfig $appConfig,
		private SecretService $secretService,
		ICacheFactory $cacheFactory,
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
		$this->cache = $cacheFactory->createDistributed(Application::APP_ID);
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function test(string $userId): array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$accountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		//		$accountId = $this->config->getUserValue($userId, Application::APP_ID, 'user_id');
		//		return $this->request($userId, Application::AUDIENCE_CORE, 'accounts/' . $accountId);
		//				$accountId = 'e3504dbb-df3c-42c5-95a7-eb64a5a302f1';
		return $this->request($userId, Application::AUDIENCE_CORE, 'v2/accounts/' . $accountId . '/mapRecords');
		//		return $this->request($userId, Application::AUDIENCE_CORE, 'mapRecords/');
	}

	public function getImage(string $url): array {
		$response = $this->client->get($url);
		return [
			'body' => $response->getBody(),
			'headers' => $response->getHeaders(),
		];
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getFavoritesWithPosition(string $userId): array {
		$allFavs = $this->getAllFavorites($userId);

		//// METHOD 1: get from top on each map (slow)
		//foreach ($allFavs as $k => $fav) {
		//	$pos = $this->getMyPositionFromTop($userId, $fav['uid']);
		//	$allFavs[$k]['myPosition'] = $pos;
		//}

		$allMyPbs = $this->getMapRecords($userId);
		$allMyPbsByMapId = [];
		foreach ($allMyPbs as $pb) {
			$allMyPbsByMapId[$pb['mapId']] = $pb;
		}

		//// METHOD 2: one by one
		//foreach ($allFavs as $k => $fav) {
		//	$mapId = $fav['mapId'];
		//	if (isset($allMyPbsByMapId[$mapId])) {
		//		$time = $allMyPbsByMapId[$mapId]['recordScore']['time'];
		//		$allFavs[$k]['myRecordTime'] = $time;
		//		$allFavs[$k]['myRecordPosition'] = $this->getScorePosition($userId, $fav['uid'], $time);
		//	} else {
		//		$allFavs[$k]['myRecordTime'] = null;
		//		$allFavs[$k]['myRecordPosition'] = null;
		//	}
		//}


		// METHOD 3: all at once
		$allMyPbTimesByMapUid = [];
		foreach ($allFavs as $fav) {
			$time = $allMyPbsByMapId[$fav['mapId']]['recordScore']['time'];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$fav['uid']] = $time;
			}
		}
		$positionsByMapUid = $this->getScorePositions($userId, $allMyPbTimesByMapUid);
		$results = [];
		foreach ($allFavs as $k => $fav) {
			$oneResult = [
				'record' => $allMyPbsByMapId[$fav['mapId']],
				'mapInfo' => $fav,
			];
			$mapUid = $fav['uid'];
			if (isset($allMyPbTimesByMapUid[$mapUid])) {
				$oneResult['recordPosition'] = $positionsByMapUid[$mapUid];
			} else {
				$oneResult['recordPosition'] = null;
			}
			$results[] = $oneResult;
		}

		return $this->formatMapResults($results);
	}

	/**
	 * Partial loading flow
	 *
	 * @param string $userId
	 * @param array|null $mapIdList
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapRecordsAndFavorites(string $userId, ?array $mapIdList = null): array {
		// get favorites because liveMapInfo always says favorite: false
		$allFavs = $this->getAllFavorites($userId);
		$allFavsByMapId = [];
		foreach ($allFavs as $fav) {
			$allFavsByMapId[$fav['mapId']] = 1;
		}

		$pbs = $this->getMapRecords($userId, null, $mapIdList);
		// $pbs = array_slice($pbs, 0, 100);
		return $this->formatRecordsAndFavorites($pbs, $allFavsByMapId);
	}

	public function formatRecordsAndFavorites(array $pbs, array $allFavsByMapId): array {
		return array_map(static function (array $pb) use ($allFavsByMapId) {
			$mapId = $pb['mapId'];
			return [
				'record' => [
					'accountId' => $pb['accountId'],
					'medal' => $pb['medal'],
					'recordScore' => $pb['recordScore'],
					'unix_timestamp' => (new DateTime($pb['timestamp']))->getTimestamp(),
				],
				'mapInfo' => [
					'favorite' => isset($allFavsByMapId[$mapId]) && $allFavsByMapId[$mapId] === 1,
					'mapId' => $mapId,
				],
			];
		}, $pbs);
	}

	/**
	 * @param string $userId
	 * @param array $pbTimesByMapId
	 * @param string|null $otherAccountId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 * @throws \DateMalformedStringException
	 */
	public function getMapsInfoAndRecordPositions(string $userId, array $pbTimesByMapId, ?string $otherAccountId): array {
		$coreMapInfos = $this->getCoreMapInfo($userId, array_keys($pbTimesByMapId));
		$coreMapInfoByMapId = [];
		$allMyPbTimesByMapUid = [];
		foreach ($coreMapInfos as $mapInfo) {
			$coreMapInfoByMapId[$mapInfo['mapId']] = $mapInfo;
			$time = $pbTimesByMapId[$mapInfo['mapId']];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$mapInfo['mapUid']] = $time;
			}
		}
		$positionsByMapUid = $this->getScorePositions($userId, $allMyPbTimesByMapUid);
		$results = [];
		foreach ($pbTimesByMapId as $mapId => $time) {
			if (isset($coreMapInfoByMapId[$mapId])) {
				$mapUid = $coreMapInfoByMapId[$mapId]['mapUid'];
				$results[$mapId] = $this->formatMapInfoAndRecordPosition($coreMapInfoByMapId[$mapId], $positionsByMapUid[$mapUid]);
			}
		}

		if ($otherAccountId !== null) {
			// get all other records by map ID
			$otherAccountRecords = $this->getMapRecords($userId, [$otherAccountId], array_keys($pbTimesByMapId));
			if (isset($otherAccountRecords['error'])) {
				return $results;
			}
			$otherRecordsByMapId = [];
			foreach ($otherAccountRecords as $record) {
				$otherRecordsByMapId[$record['mapId']] = $record;
			}

			// get all other times by map UID
			$allOtherPbTimesByMapUid = [];
			foreach ($coreMapInfos as $mapInfo) {
				$time = $otherRecordsByMapId[$mapInfo['mapId']]['recordScore']['time'] ?? null;
				if ($time !== null) {
					$allOtherPbTimesByMapUid[$mapInfo['mapUid']] = $time;
				}
			}

			foreach ($pbTimesByMapId as $mapId => $time) {
				if (isset($coreMapInfoByMapId[$mapId])) {
					$mapUid = $coreMapInfoByMapId[$mapId]['mapUid'];
					if (isset($allOtherPbTimesByMapUid[$mapUid])) {
						/*
						if ($allOtherPbTimesByMapUid[$mapUid] < $allMyPbTimesByMapUid[$mapUid]) {
							$position = $this->getScorePosition($userId, $mapUid, $allOtherPbTimesByMapUid[$mapUid]);
						} else {
							$position = $this->getAccountPositionFromTop($userId, $mapUid, $otherAccountId);
						}
						*/
						$results[$mapId]['otherRecord'] = [
							'time' => $allOtherPbTimesByMapUid[$mapUid],
							'record' => $otherRecordsByMapId[$mapId],
							'unix_timestamp' => (new DateTime($otherRecordsByMapId[$mapId]['timestamp']))->getTimestamp(),
							// 'position' => $position,
						];
					}
				}
			}
		}

		return $results;
	}

	public function formatMapInfoAndRecordPosition(array $mapInfo, array $position): array {
		$formatted = [
			'mapInfo' => [
				'uid' => $mapInfo['mapUid'],
				'mapId' => $mapInfo['mapId'],
				'name' => $mapInfo['name'],
				'author' => $mapInfo['author'],
				'authorName' => $mapInfo['authorName'],
				'authorTime' => $mapInfo['authorScore'],
				'goldTime' => $mapInfo['goldScore'],
				'silverTime' => $mapInfo['silverScore'],
				'bronzeTime' => $mapInfo['bronzeScore'],
				'thumbnailUrl' => $mapInfo['thumbnailUrl'],
			],
			'recordPosition' => [
				'score' => $position['score'],
				'zones' => [],
			],
		];
		foreach ($position['zones'] as $zone) {
			$formatted['recordPosition']['zones'][$zone['zoneName']] = $zone['ranking']['position'];
		}
		return $formatted;
	}
	// END Partial loading flow

	/**
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getAllMapsWithPosition(string $userId): array {
		// get favorites because liveMapInfo always says favorite: false
		$allFavs = $this->getAllFavorites($userId);
		$allFavsByMapId = [];
		foreach ($allFavs as $fav) {
			$allFavsByMapId[$fav['mapId']] = 1;
		}

		$pbs = $this->getMapRecords($userId);
		//		$pbs = array_slice($pbs, 0, 100);
		$pbTimesByMapId = [];
		foreach ($pbs as $pb) {
			$pbTimesByMapId[$pb['mapId']] = $pb['recordScore']['time'];
		}
		$coreMapInfos = $this->getCoreMapInfo($userId, array_keys($pbTimesByMapId));
		$coreMapInfoByMapId = [];
		$allMyPbTimesByMapUid = [];
		foreach ($coreMapInfos as $mapInfo) {
			$coreMapInfoByMapId[$mapInfo['mapId']] = $mapInfo;
			$time = $pbTimesByMapId[$mapInfo['mapId']];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$mapInfo['mapUid']] = $time;
			}
		}
		/*
		// there is more information in the live endpoint but nothing we really need
		$liveMapInfos = $this->getLiveMapInfo($userId, array_keys($allMyPbTimesByMapUid));
		$liveMapInfoByMapId = [];
		foreach ($liveMapInfos as $mapInfo) {
			$liveMapInfoByMapId[$mapInfo['mapId']] = $mapInfo;
		}
		*/
		$positionsByMapUid = $this->getScorePositions($userId, $allMyPbTimesByMapUid);
		$results = [];
		foreach ($pbs as $k => $pb) {
			$oneResult = [
				'record' => $pb,
			];
			$mapId = $pb['mapId'];
			if (isset($coreMapInfoByMapId[$mapId])) {
				$mapUid = $coreMapInfoByMapId[$mapId]['mapUid'];
				$oneResult['mapInfo'] = $coreMapInfoByMapId[$mapId];
				$oneResult['mapInfo']['favorite'] = isset($allFavsByMapId[$mapId]);
				if (isset($allMyPbTimesByMapUid[$mapUid])) {
					$oneResult['recordPosition'] = $positionsByMapUid[$mapUid];
				} else {
					$oneResult['recordPosition'] = null;
				}
			}
			$results[] = $oneResult;
		}

		return $this->formatMapResults($results);
	}

	public function formatMapResults(array $data): array {
		return array_map(static function (array $item) {
			$formatted = [
				'record' => [
					'accountId' => $item['record']['accountId'],
					'medal' => $item['record']['medal'],
					'recordScore' => $item['record']['recordScore'],
					'unix_timestamp' => (new DateTime($item['record']['timestamp']))->getTimestamp(),
				],
				'mapInfo' => [
					'uid' => $item['mapInfo']['mapUid'],
					'mapId' => $item['mapInfo']['mapId'],
					'name' => $item['mapInfo']['name'],
					'favorite' => $item['mapInfo']['favorite'],
					'author' => $item['mapInfo']['author'],
					'authorName' => $item['mapInfo']['authorName'],
					'authorTime' => $item['mapInfo']['authorScore'],
					'goldTime' => $item['mapInfo']['goldScore'],
					'silverTime' => $item['mapInfo']['silverScore'],
					'bronzeTime' => $item['mapInfo']['bronzeScore'],
					'thumbnailUrl' => $item['mapInfo']['thumbnailUrl'],
				],
				'recordPosition' => [
					'score' => $item['recordPosition']['score'],
					'zones' => [],
				],
			];
			foreach ($item['recordPosition']['zones'] as $zone) {
				$formatted['recordPosition']['zones'][$zone['zoneName']] = $zone['ranking']['position'];
			}
			return $formatted;
		}, $data);
	}

	/**
	 * @param string $userId
	 * @param array|null $mapIds
	 * @param array|null $mapUids
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getCoreMapInfo(string $userId, ?array $mapIds = null, ?array $mapUids = null): array {
		if ($mapIds !== null) {
			$paramName = 'mapIdList';
			$cachePrefix = 'id';
			$itemKeyInMapInfo = 'mapId';
			$itemList = $mapIds;
		} elseif ($mapUids !== null) {
			$paramName = 'mapUidList';
			$cachePrefix = 'uid';
			$itemKeyInMapInfo = 'mapUid';
			$itemList = $mapUids;
		} else {
			return [];
		}

		$mapInfos = [];

		// get cached
		$nonCachedItems = [];
		foreach ($itemList as $item) {
			$cacheKey = 'core-map-' . $cachePrefix . '-' . $item;
			$cachedMapInfo = $this->cache->get($cacheKey);
			if ($cachedMapInfo !== null) {
				$mapInfos[] = $cachedMapInfo;
			} else {
				$nonCachedItems[] = $item;
			}
		}

		$offset = 0;
		while ($offset < count($nonCachedItems)) {
			$oneRequestItemList = [];
			$stringListLength = 0;
			while ($stringListLength < 7000 && $offset < count($nonCachedItems)) {
				$oneRequestItemList[] = $nonCachedItems[$offset];
				$stringListLength += strlen($nonCachedItems[$offset]) + 1;
				$offset++;
			}
			$params = [
				$paramName => implode(',', $oneRequestItemList),
			];
			// max URI length: 8220 chars
			$oneChunk = $this->request($userId, Application::AUDIENCE_CORE, 'maps/', $params);
			if (!isset($oneChunk['error'])) {
				$mapInfos = array_merge($mapInfos, $oneChunk);
			}
			// cache this chunk
			foreach ($oneChunk as $mapInfo) {
				$cacheKey = 'core-map-' . $cachePrefix . '-' . $mapInfo[$itemKeyInMapInfo];
				$this->cache->set($cacheKey, $mapInfo, Application::CACHE_DURATION);
			}
		}

		// get map author names
		$authorIds = array_map(function (array $mapInfo) {
			return $mapInfo['author'];
		}, $mapInfos);
		$authorIds = array_unique($authorIds);
		$authorNames = $this->getAuthorNames($authorIds);
		return array_map(
			static function (array $mapInfo) use ($authorNames) {
				$mapInfo['authorName'] = $authorNames[$mapInfo['author']] ?? '???';
				return $mapInfo;
			},
			$mapInfos,
		);
	}

	/**
	 * @param array $authorIds
	 * @return string[]
	 * @throws Exception
	 */
	private function getAuthorNames(array $authorIds): array {
		$authorNames = ['d2372a08-a8a1-46cb-97fb-23a161d85ad0' => 'Nadeo'];

		// get cached
		$nonCachedAuthorNames = [];
		foreach ($authorIds as $authorId) {
			$cacheKey = 'author-name-' . $authorId;
			$cachedAuthorName = $this->cache->get($cacheKey);
			if ($cachedAuthorName !== null) {
				$authorNames[$authorId] = $cachedAuthorName;
			} else {
				$nonCachedAuthorNames[] = $authorId;
			}
		}

		if (empty($nonCachedAuthorNames)) {
			return $authorNames;
		}

		$accessToken = $this->getOAuthToken();
		$url = 'https://api.trackmania.com/api/display-names';
		$options = [
			'headers' => [
				'User-Agent' => Application::INTEGRATION_USER_AGENT,
				'Authorization' => 'Bearer ' . $accessToken,
			],
		];

		$chunkSize = 50;
		$offset = 0;
		while ($offset < count($nonCachedAuthorNames)) {
			$authorIdsToLook = array_slice($nonCachedAuthorNames, $offset, $chunkSize);
			$getParamsString = implode(
				'&',
				array_map(
					static function (string $authorId) {
						return 'accountId[]=' . $authorId;
					},
					$authorIdsToLook,
				)
			);
			$response = $this->client->get($url . '?' . $getParamsString, $options);
			$oneChunk = json_decode($response->getBody(), true);
			$authorNames = array_merge($authorNames, $oneChunk);
			$offset = $offset + $chunkSize;
			// cache this chunk
			foreach ($oneChunk as $authorId => $authorName) {
				$cacheKey = 'author-name-' . $authorId;
				$this->cache->set($cacheKey, $authorName, Application::CACHE_DURATION);
			}
		}

		return $authorNames;
	}

	/**
	 * @param string $userId
	 * @param array $mapUids
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getLiveMapInfo(string $userId, array $mapUids): array {
		$results = [];
		// get cached
		$nonCachedMapUids = [];
		foreach ($mapUids as $uid) {
			$cacheKey = 'live-map-' . $uid;
			$cachedMapInfo = $this->cache->get($cacheKey);
			if ($cachedMapInfo !== null) {
				$results[] = $cachedMapInfo;
			} else {
				$nonCachedMapUids[] = $uid;
			}
		}

		$chunkSize = 100;
		$offset = 0;
		while ($offset < count($nonCachedMapUids)) {
			$uidsToLook = array_slice($nonCachedMapUids, $offset, $chunkSize);
			$params = [
				'mapUidList' => implode(',', $uidsToLook),
			];
			$oneChunk = $this->request($userId, Application::AUDIENCE_LIVE, 'map/get-multiple', $params);
			if (isset($oneChunk['mapList'])) {
				$results = array_merge($results, $oneChunk['mapList']);
			}
			$offset = $offset + $chunkSize;
			// cache this chunk
			foreach ($oneChunk['mapList'] as $mapInfo) {
				$cacheKey = 'live-map-' . $mapInfo['uid'];
				$this->cache->set($cacheKey, $mapInfo, Application::CACHE_DURATION);
			}
		}

		return $results;
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param string $action
	 * @return array|array[]|resource[]|string[]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function toggleFavorite(string $userId, string $mapUid, string $action): array {
		if ($action === 'add' || $action === 'remove') {
			$response = $this->request($userId, Application::AUDIENCE_LIVE, 'map/favorite/' . $mapUid . '/' . $action, [], 'POST', false);
			return ['body' => $response];
		}
		return [];
	}

	public function getAllFavorites(string $userId): array {
		$maps = [];
		$chunkSize = 200;
		$favs = $this->getFavoriteMaps($userId, 0, $chunkSize);
		if (isset($favs['itemCount']) && is_numeric($favs['itemCount'])) {
			$nbMaps = $favs['itemCount'];
			$maps = array_merge($maps, $favs['mapList']);
			while (count($maps) < $nbMaps || isset($favs['error'])) {
				$favs = $this->getFavoriteMaps($userId, count($maps), $chunkSize);
				$maps = array_merge($maps, $favs['mapList']);
			}
		}
		return $maps;
	}

	public function getFavoriteMaps(string $userId, int $offset = 0, int $limit = 20): array {
		$params = [
			'offset' => $offset,
			'length' => $limit,
		];
		return $this->request($userId, Application::AUDIENCE_LIVE, 'map/favorite', $params);
	}

	/**
	 * @param string $userId
	 * @param array|null $accountIds connected account is used if null (or if $mapIds is null)
	 * @param array|null $mapIds all records are retrieved if null (only works with the connected account)
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapRecords(string $userId, ?array $accountIds = null, ?array $mapIds = null): array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];

		$userAccountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		if ($mapIds === null) {
			$params = [
				'limit' => 1000,
				'offset' => 0,
			];
			$records = [];
			do {
				$newRecords = $this->request($userId, Application::AUDIENCE_CORE, 'v2/accounts/' . $userAccountId . '/mapRecords/', $params);
				$records = array_merge($records, $newRecords);
				$lastSize = count($newRecords);
				$params['offset'] = $params['offset'] + 1000;
			} while ($lastSize === 1000);
			return $records;
		}

		$params = [
			'accountIdList' => $accountIds === null ? $userAccountId : implode(',', $accountIds),
		];
		return array_reduce(
			$mapIds,
			function ($carry, $mapId) use ($userId, $params) {
				$params['mapId'] = $mapId;
				// max URI length: 8220 chars
				$records = $this->request($userId, Application::AUDIENCE_CORE, 'v2/mapRecords/', $params);
				return array_merge($carry, $records);
			},
			[]
		);
	}

	public function getScorePositions(string $userId, array $scoresByMapUid): array {
		$positionsByMapUid = [];
		$uids = array_keys($scoresByMapUid);
		$chunkSize = 50;
		$offset = 0;
		while ($offset < count($uids)) {
			$uidsToLook = array_slice($uids, $offset, $chunkSize);
			$params = [
				'maps' => [],
			];
			foreach ($uidsToLook as $uid) {
				$params['maps'][] = [
					'mapUid' => $uid,
					'groupUid' => 'Personal_Best',
				];
			}
			$getParams = array_map(function ($uid) use ($scoresByMapUid) {
				return 'scores[' . $uid . ']=' . $scoresByMapUid[$uid];
			}, $uidsToLook);
			$positions = $this->request($userId, Application::AUDIENCE_LIVE, 'leaderboard/group/map?' . implode('&', $getParams), $params, 'POST');
			if (!isset($positions['error'])) {
				foreach ($positions as $position) {
					$positionsByMapUid[$position['mapUid']] = $position;
				}
			}
			$offset = $offset + $chunkSize;
		}

		return $positionsByMapUid;
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $score
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getScorePosition(string $userId, string $mapUid, int $score): array {
		$params = [
			'maps' => [
				[
					'mapUid' => $mapUid,
					'groupUid' => 'Personal_Best',
				],
			],
		];
		return $this->request($userId, Application::AUDIENCE_LIVE, 'leaderboard/group/map?scores[' . $mapUid . ']=' . $score, $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $score
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getScoreImprovements(string $userId, string $mapUid, int $score): array {
		$improvements = [1, 10, 100, 1000];
		$results = [];
		foreach ($improvements as $improvement) {
			$results[$improvement] = $this->getScorePosition($userId, $mapUid, $score - $improvement);
		}
		return $results;
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $offset
	 * @param int $length
	 * @param bool $onlyWorld
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapTop(string $userId, string $mapUid, int $offset = 0, int $length = 20, bool $onlyWorld = true): array {
		$params = [
			'onlyWorld' => $onlyWorld ? 'true' : 'false',
			'offset' => $offset,
			'length' => $length,
		];
		return $this->request($userId, Application::AUDIENCE_LIVE, 'leaderboard/group/Personal_Best/map/' . $mapUid . '/top', $params);
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $position
	 * @return bool
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	private function positionExists(string $userId, string $mapUid, int $position): bool {
		$top = $this->getMapTop($userId, $mapUid, $position, 1);
		// sometimes asking for bigger position than the max always returns the biggest
		// so we have to check the one we get is the one we want, if not, the one we want probably does not exist
		return (isset($top['tops']) && is_array($top['tops']) && count($top['tops']) > 0
			&& isset($top['tops'][0]['top']) && is_array($top['tops'][0]['top']) && count($top['tops'][0]['top']) > 0
			&& $top['tops'][0]['top'][0]['position'] === $position + 1);
	}

	public function getMapFinishCount3rdParty(string $mapUid): ?int {
		$url = 'https://tm.waalrus.xyz/np/map/' . $mapUid;
		try {
			$response = $this->client->get($url);
			$body = $response->getBody();
			$parsedBody = json_decode($body, true);
			if (is_array($parsedBody) && isset($parsedBody['player_count']) && is_numeric($parsedBody['player_count'])) {
				return (int)$parsedBody['player_count'];
			}
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Failed to get nb players from tm.waalrus.xyz', ['app' => Application::APP_ID, 'exception' => $e]);
		}

		$url = 'https://map-monitor.xk.io/map/' . $mapUid . '/nb_players/refresh';
		try {
			$response = $this->client->get($url);
			$body = $response->getBody();
			$parsedBody = json_decode($body, true);
			if (is_array($parsedBody) && isset($parsedBody['nb_players']) && is_numeric($parsedBody['nb_players'])) {
				return (int)$parsedBody['nb_players'];
			}
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Failed to get nb players from map-monitor.xk.io', ['app' => Application::APP_ID, 'exception' => $e]);
		}

		return null;
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @return int
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapFinishCount(string $userId, string $mapUid): int {
		$positionFrom3rdParty = $this->getMapFinishCount3rdParty($mapUid);
		if ($positionFrom3rdParty !== null) {
			return $positionFrom3rdParty;
		}

		if (!$this->positionExists($userId, $mapUid, 0)) {
			return 0;
		}

		// if no more than 10000 finishes, explore 0-10000
		if (!$this->positionExists($userId, $mapUid, 10000)) {
			return $this->getMapFinishCountDico($userId, $mapUid, 0, 10000);
		}

		// find an offset bigger than the number of finishes
		$minPosition = 10000;
		$maxPosition = 20000;
		while ($this->positionExists($userId, $mapUid, $maxPosition)) {
			$minPosition = $maxPosition;
			$maxPosition = $maxPosition * 2;
		}
		return $this->getMapFinishCountDico($userId, $mapUid, $minPosition, $maxPosition);
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $minPosition
	 * @param int $maxPosition
	 * @return int
	 */
	private function getMapFinishCountDico(string $userId, string $mapUid, int $minPosition, int $maxPosition): int {
		if ($maxPosition - $minPosition === 1) {
			return $this->positionExists($userId, $mapUid, $maxPosition) ? $maxPosition + 1 : $minPosition + 1;
		} elseif ($maxPosition - $minPosition === 0) {
			return $minPosition + 1;
		} else {
			$middlePosition = intdiv($minPosition + $maxPosition, 2);
			return $this->positionExists($userId, $mapUid, $middlePosition)
				? $this->getMapFinishCountDico($userId, $mapUid, $middlePosition, $maxPosition)
				: $this->getMapFinishCountDico($userId, $mapUid, $minPosition, $middlePosition);
		}
	}

	/**
	 * Works but is slow
	 *
	 * @param string $userId
	 * @param string $mapUid
	 * @param string|null $accountId
	 * @return array|null
	 * @throws PreConditionNotMetException
	 */
	public function getAccountPositionFromTop(string $userId, string $mapUid, ?string $accountId = null): ?array {
		$chunkSize = 100;
		$prefix = Application::AUDIENCES[Application::AUDIENCE_LIVE]['token_config_key_prefix'];
		$accountId = $accountId === null
			? $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id')
			: $accountId;
		$pos = null;
		$offset = 0;
		while ($pos === null && $offset < 10000) {
			$top = $this->getMapTop($userId, $mapUid, $offset, $chunkSize);
			if (isset($top['error'])) {
				return null;
			}
			$pos = $this->findPositionByAccountId($accountId, $top);
			$offset = $offset + $chunkSize;
		}
		return $pos;
	}

	public function findPositionByAccountId(string $accountId, array $top): ?array {
		if (isset($top['tops']) && is_array($top['tops']) && count($top['tops']) === 1) {
			$positions = $top['tops'][0]['top'];
			foreach ($positions as $position) {
				if ($position['accountId'] === $accountId) {
					return $position;
				}
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @return array
	 */
	public function searchAccount(string $name): array {
		$cacheKey = 'tmio-player-find-' . $name;
		$cachedSearchResult = $this->cache->get($cacheKey);
		if ($cachedSearchResult !== null) {
			return $cachedSearchResult;
		}
		$params = [
			'search' => $name,
		];
		$searchResult = $this->requestTrackmaniaIo('players/find', $params);
		$this->cache->set($cacheKey, $searchResult, Application::CACHE_DURATION);
		return $searchResult;
	}

	/**
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string
	 */
	public function requestTrackmaniaIo(string $endPoint, array $params = [], string $method = 'GET', bool $jsonResponse = true) {
		try {
			$url = Application::TRACKMANIA_IO_API_URL . $endPoint;
			$options = [
				'headers' => [
					'Content-Type' => 'application/json',
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['json'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($jsonResponse) {
					return json_decode($body, true);
				} else {
					return $body;
				}
			}
		} catch (ServerException|ClientException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('Trackmania.io API error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Trackmania.io API error', ['exception' => $e, 'app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @param string $audience
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function request(
		string $userId, string $audience, string $endPoint, array $params = [], string $method = 'GET', bool $jsonResponse = true,
	) {
		$this->checkTokenExpiration($userId, $audience);
		$accessToken = $this->secretService->getEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token');
		try {
			$url = Application::AUDIENCES[$audience]['base_url'] . $endPoint;
			$options = [
				'headers' => [
					'Authorization' => 'nadeo_v1 t=' . $accessToken,
					'Content-Type' => 'application/json',
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['json'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				if ($jsonResponse) {
					return json_decode($body, true);
				} else {
					return $body;
				}
			}
		} catch (ClientException|ServerException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('API error Client/Server exception: ' . $body, ['exception' => $e]);
			throw new TmApiRequestException($e, $audience, $endPoint, $method, $params);
		}
	}

	/**
	 * @param string $userId
	 * @param string $audience
	 * @return void
	 * @throws PreConditionNotMetException
	 * @throws TokenRefreshException
	 */
	private function checkTokenExpiration(string $userId, string $audience): void {
		$refreshToken = $this->secretService->getEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new DateTime())->getTimestamp();
			$expireAt = (int)$expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId, $audience);
			}
		}
	}

	/**
	 * @param string $userId
	 * @param string $audience
	 * @return bool
	 * @throws PreConditionNotMetException
	 * @throws TokenRefreshException
	 */
	private function refreshToken(string $userId, string $audience): bool {
		$refreshToken = $this->secretService->getEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No ' . $audience . ' refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		try {
			$url = Application::TOKEN_REFRESH_URL;
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Authorization' => 'nadeo_v1 t=' . $refreshToken,
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return false;
			} else {
				$bodyArray = json_decode($body, true);
				if (isset($bodyArray['accessToken'], $bodyArray['refreshToken'])) {
					$this->logger->info($audience . 'access token successfully refreshed', ['app' => Application::APP_ID]);
					$accessToken = $bodyArray['accessToken'];
					$refreshToken = $bodyArray['refreshToken'];
					$this->secretService->setEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token', $accessToken);
					$this->secretService->setEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token', $refreshToken);

					$prefix = Application::AUDIENCES[$audience]['token_config_key_prefix'];
					$decodedToken = ConfigController::decodeToken($accessToken);
					$expiresAt = $decodedToken['exp'];
					$this->config->setUserValue($userId, Application::APP_ID, $prefix . 'token_expires_at', $expiresAt);
					return true;
				}
				return false;
			}
		} catch (ClientException $e) {
			$response = $e->getResponse();
			$statusCode = $response->getStatusCode();
			if ($statusCode === Http::STATUS_UNAUTHORIZED) {
				$body = $response->getBody();
				$parsedResponse = json_decode($body, true);
				$this->logger->error(
					$audience . ' token is not valid anymore. Impossible to refresh it. '
					. ($parsedResponse['message'] ?? '[no error message]'),
					['app' => Application::APP_ID]
				);
			}
			throw new TokenRefreshException($e);
		}
	}

	/**
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function login(string $login, string $password): array {
		try {
			$url = 'https://public-ubiservices.ubi.com/v3/profiles/sessions';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
					'Ubi-AppId' => '86263886-327a-4328-ac69-527f0d20a237',
					'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid credentials')];
			} else {
				$bodyArray = json_decode($body, true);
				if (isset($bodyArray['ticket'], $bodyArray['userId'], $bodyArray['nameOnPlatform'])) {
					foreach (Application::AUDIENCES as $audienceKey => $v) {
						$tokens = $this->getAccessTokenFromLoginTicket($bodyArray['ticket'], $audienceKey);
						if (isset($tokens['accessToken'], $tokens['refreshToken'])) {
							$bodyArray[$audienceKey] = $tokens;
						}
					}
					return $bodyArray;
				}
				return ['error' => $this->l10n->t('Error during login')];
			}
		} catch (Exception $e) {
			$this->logger->warning('login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $ticket
	 * @param string $audience
	 * @return array
	 */
	public function getAccessTokenFromLoginTicket(string $ticket, string $audience): array {
		try {
			$url = 'https://prod.trackmania.core.nadeo.online/v2/authentication/token/ubiservices';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
					'Authorization' => 'ubi_v1 t=' . $ticket,
				],
				'json' => [
					'audience' => $audience,
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid credentials')];
			} else {
				$bodyArray = json_decode($body, true);
				if (isset($bodyArray['accessToken'], $bodyArray['refreshToken'])) {
					return $bodyArray;
				}
				return ['error' => $this->l10n->t('Error when getting access token')];
			}
		} catch (Exception $e) {
			$this->logger->warning('login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @return string
	 * @throws AppConfigTypeConflictException
	 */
	public function getOAuthToken(): string {
		$accessToken = $this->appConfig->getValueString(Application::APP_ID, 'access_token');
		$expiresAt = $this->appConfig->getValueInt(Application::APP_ID, 'oauth_token_expires_at', -1);
		if ($accessToken === '' || $expiresAt === -1 || $expiresAt < time()) {
			return $this->getNewOAuthToken();
		}
		return $accessToken;
	}

	/**
	 * @return string
	 * @throws AppConfigTypeConflictException
	 */
	public function getNewOAuthToken(): string {
		$clientId = $this->appConfig->getValueString(Application::APP_ID, 'client_id');
		$clientSecret = $this->appConfig->getValueString(Application::APP_ID, 'client_secret');
		if ($clientId === '' || $clientSecret === '') {
			throw new \Exception('No client id or secret set');
		}
		try {
			$url = 'https://api.trackmania.com/api/access_token';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
				],
				'json' => [
					'grant_type' => 'client_credentials',
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				throw new Exception('Invalid credentials');
			} else {
				$bodyArray = json_decode($body, true);
				if (isset($bodyArray['access_token'], $bodyArray['expires_in'])) {
					$this->appConfig->setValueString(Application::APP_ID, 'access_token', $bodyArray['access_token'], false, true);

					$expiresIn = (int)$bodyArray['expires_in'];
					$expiresAt = time() + $expiresIn;
					$this->appConfig->setValueInt(Application::APP_ID, 'oauth_token_expires_at', $expiresAt);
					return $bodyArray['access_token'];
				}
				throw new Exception('No access token in OAuth response');
			}
		} catch (Exception $e) {
			$this->logger->warning('Get OAuth token error: ' . $e->getMessage(), ['exception' => $e]);
			throw $e;
		}
	}
}
