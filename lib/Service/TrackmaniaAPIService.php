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
use Generator;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Controller\ConfigController;
use OCA\Trackmania\Db\TrackPosition;
use OCA\Trackmania\Db\TrackPositionMapper;
use OCA\Trackmania\Exception\TmApiRequestException;
use OCA\Trackmania\Exception\TokenRefreshException;
use OCA\Trackmania\Util\SkinZipPreview;
use OCA\Trackmania\Util\TgaImageConverter;
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

	/** How many requests are sent at once when fetching records of one account on a map list */
	private const CONCURRENT_RECORD_REQUESTS = 8;
	/** How many leaderboard entries are fetched per page when computing a rank */
	private const RANK_PAGE_SIZE = 100;
	/** Ranks change rarely, cache their info a bit, they are based on the board state of the last hour */
	private const RANK_CACHE_TTL = 60 * 60;
	/** Records of another account on a map change rarely, this cache avoids refetching them on every reload */
	private const MAP_RECORD_CACHE_TTL = 60 * 60;
	/** Max size of an upstream records page */
	private const OWN_RECORDS_PAGE_SIZE = 1000;
	/** Skins change rarely, cache their info a bit */
	private const SKIN_INFO_CACHE_TTL = 24 * 60 * 60;
	/** Skin preview images are also cached a bit, failures included, to not probe the same skin repeatedly */
	private const SKIN_THUMBNAIL_CACHE_TTL = 24 * 60 * 60;
	/** How much of the end of the skin ZIP has to be read to find the central directory descriptor */
	private const ZIP_EOCD_SEARCH_SIZE = 65536;

	private IClient $client;
	private ICache $cache;

	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private IAppConfig $appConfig,
		private SecretService $secretService,
		private TrackPositionMapper $trackPositionMapper,
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

	public function isUserConnected(string $userId): bool {
		$token = $this->secretService->getEncryptedUserValue($userId, Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'] . 'token');
		return $token !== '';
	}

	/**
	 * Get the map IDs of all favorites of the current account.
	 * Upstream favorites are paginated by pages of getAllFavorites()'s internal chunk size.
	 *
	 * @param string $userId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getAllFavoriteMapIds(string $userId): array {
		return array_map(static function (array $favorite) {
			return $favorite['mapId'];
		}, $this->getAllFavorites($userId));
	}

	/**
	 * Get one page of all current account records.
	 * The upstream endpoint does not provide the total number of records,
	 * use 'hasMoreRecords' to know whether another page can be fetched.
	 * The favorite flag of each record is merged on the frontend from the fetched favorites.
	 *
	 * @param string $userId
	 * @param int $offset
	 * @return array ['records' => array, 'hasMoreRecords' => bool]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getRawRecordsPage(string $userId, int $offset): array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$userAccountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');

		$rawRecords = $this->getMapRecordsPage($userId, $userAccountId, $offset);
		$records = [];
		foreach ($rawRecords as $rawRecord) {
			$records[] = [
				'record' => [
					'accountId' => $rawRecord['accountId'],
					'medal' => $rawRecord['medal'],
					'recordScore' => $rawRecord['recordScore'],
					'unix_timestamp' => (new DateTime($rawRecord['timestamp']))->getTimestamp(),
				],
				'mapInfo' => [
					'mapId' => $rawRecord['mapId'],
				],
			];
		}
		return [
			'records' => $records,
			// the total number of records is unknown upstream, page until one comes back incomplete
			'hasMoreRecords' => count($rawRecords) === self::OWN_RECORDS_PAGE_SIZE,
		];
	}

	/**
	 * Get one page of the records of an account.
	 *
	 * @param string $userId
	 * @param string $userAccountId
	 * @param int $offset
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	private function getMapRecordsPage(string $userId, string $userAccountId, int $offset): array {
		return $this->request($userId, Application::AUDIENCE_CORE, 'v2/accounts/' . $userAccountId . '/mapRecords/', [
			'limit' => self::OWN_RECORDS_PAGE_SIZE,
			'offset' => $offset,
		]);
	}

	/**
	 * @param string $userId
	 * @param array $pbTimesByMapId
	 * @param string|null $otherAccountId
	 * @return array
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapsInfoAndRecordPositions(
		string $userId, array $pbTimesByMapId, ?string $otherAccountId, bool $withWorldRecords = false,
	): array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$userAccountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');

		$coreMapInfos = $this->getCoreMapInfo($userId, array_keys($pbTimesByMapId));
		$coreMapInfos = iterator_to_array($coreMapInfos);
		$coreMapInfoByMapUid = [];
		$coreMapInfoByMapId = [];
		$allMyPbTimesByMapUid = [];
		foreach ($coreMapInfos as $mapInfo) {
			$coreMapInfoByMapUid[$mapInfo['mapUid']] = $mapInfo;
			$coreMapInfoByMapId[$mapInfo['mapId']] = $mapInfo;
			$time = $pbTimesByMapId[$mapInfo['mapId']];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$mapInfo['mapUid']] = $time;
			}
		}
		$positions = iterator_to_array($this->getScorePositions($userId, $allMyPbTimesByMapUid));

		// prepare the World positions to batch-save and the maps to format
		$positionUpdatesByTrackId = [];
		$formattedMapIds = [];
		foreach ($positions as $position) {
			$mapUid = $position['mapUid'];
			if (isset($coreMapInfoByMapUid[$mapUid])) {
				$mapId = $coreMapInfoByMapUid[$mapUid]['mapId'];
				$formattedMapIds[] = $mapId;
				foreach ($position['zones'] as $zone) {
					if ($zone['zoneName'] === 'World') {
						$positionUpdatesByTrackId[$mapId] = [
							'trackUid' => $mapUid,
							'position' => $zone['ranking']['position'],
						];
					}
				}
			}
		}
		if (count($positionUpdatesByTrackId) > 0) {
			$this->trackPositionMapper->updatePositionsOfTracks($userId, $userAccountId, $positionUpdatesByTrackId);
		}
		// best positions are fetched in one query for all maps that get formatted
		$bestPositionsByTrackId = count($formattedMapIds) > 0
			? $this->trackPositionMapper->getLastBestPositionsOfTracks($userId, $userAccountId, $formattedMapIds)
			: [];

		$worldRecordsByMapUid = [];
		if ($withWorldRecords) {
			// you are the world record holder of a map when the world position of your record is 1,
			// in that case, no leaderboard top request is needed
			$faMapUidsToFetch = [];
			foreach ($positions as $position) {
				$mapUid = $position['mapUid'];
				if (!isset($coreMapInfoByMapUid[$mapUid])) {
					continue;
				}
				$worldPosition = null;
				foreach ($position['zones'] as $zone) {
					if ($zone['zoneName'] === 'World') {
						$worldPosition = $zone['ranking']['position'];
						break;
					}
				}
				if ($worldPosition === 1) {
					$worldRecordsByMapUid[$mapUid] = [
						'time' => $position['score'],
						'account_id' => $userAccountId,
						'unix_timestamp' => null,
					];
				} else {
					$faMapUidsToFetch[] = $mapUid;
				}
			}
			$worldRecordsByMapUid = array_merge($worldRecordsByMapUid, $this->getWorldRecords($userId, $faMapUidsToFetch));
			$ownNames = $this->getAuthorNames([$userAccountId]);
			foreach ($worldRecordsByMapUid as $mapUid => $worldRecord) {
				if ($worldRecord['time'] !== null) {
					$worldRecordsByMapUid[$mapUid]['name'] = ($worldRecord['account_id'] === $userAccountId)
						? ($ownNames[$userAccountId] ?? '')
						: ($worldRecord['name'] ?? '');
				}
			}
		}

		$results = [];
		foreach ($positions as $position) {
			$mapUid = $position['mapUid'];
			if (isset($coreMapInfoByMapUid[$mapUid])) {
				$mapInfo = $coreMapInfoByMapUid[$mapUid];
				$mapId = $mapInfo['mapId'];
				$results[$mapId] = $this->formatMapInfoAndRecordPosition(
					$userId, $userAccountId, $mapInfo, $position,
					$bestPositionsByTrackId[$mapId] ?? null,
				);
				if ($withWorldRecords) {
					$results[$mapId]['worldRecord'] = $worldRecordsByMapUid[$mapUid] ?? null;
				}
			}
		}

		if ($otherAccountId !== null) {
			// get all other records by map ID
			$otherAccountRecords = $this->getMapRecords($userId, [$otherAccountId], array_keys($pbTimesByMapId));
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

			// the position endpoint computes what rank a given score would reach,
			// but it refuses to rank scores worse than the authenticated player's own record,
			// so scores worse than the own PB are left null and resolved on demand
			$otherZonesByMapUid = [];
			$ratableOtherTimesByMapUid = [];
			foreach ($allOtherPbTimesByMapUid as $mapUid => $otherTime) {
				$ownTime = $allMyPbTimesByMapUid[$mapUid] ?? null;
				if ($ownTime === null || $otherTime <= $ownTime) {
					$ratableOtherTimesByMapUid[$mapUid] = $otherTime;
				}
			}
			if (count($ratableOtherTimesByMapUid) > 0) {
				foreach ($this->getScorePositions($userId, $ratableOtherTimesByMapUid) as $otherPosition) {
					$otherZonesByMapUid[$otherPosition['mapUid']] = $otherPosition['zones'];
				}
			}

			foreach ($pbTimesByMapId as $mapId => $time) {
				if (isset($coreMapInfoByMapId[$mapId])) {
					$mapUid = $coreMapInfoByMapId[$mapId]['mapUid'];
					if (isset($allOtherPbTimesByMapUid[$mapUid])) {
						$otherRecord = [
							'time' => $allOtherPbTimesByMapUid[$mapUid],
							'record' => $otherRecordsByMapId[$mapId],
							'unix_timestamp' => (new DateTime($otherRecordsByMapId[$mapId]['timestamp']))->getTimestamp(),
						];
						if (isset($otherZonesByMapUid[$mapUid])) {
							$otherZonePositions = [];
							foreach ($otherZonesByMapUid[$mapUid] as $otherZonePosition) {
								$otherZonePositions[$otherZonePosition['zoneName']] = $otherZonePosition['ranking']['position'];
							}
							$otherRecord['position'] = [
								'score' => $allOtherPbTimesByMapUid[$mapUid],
								'zones' => $otherZonePositions,
							];
						}
						$results[$mapId]['otherRecord'] = $otherRecord;
					}
				}
			}
		}

		return $results;
	}

	public function formatMapInfoAndRecordPosition(
		string $userId, string $accountId, array $mapInfo, array $position, ?TrackPosition $bestTrackPosition,
	): array {
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

		// use the best known position which was fetched beforehand
		$formatted['bestKnownPosition'] = [
			'position' => $bestTrackPosition === null ? null : $bestTrackPosition->getPosition(),
			'first_seen_at' => $bestTrackPosition === null ? null : $bestTrackPosition->getFirstSeenAt(),
			'last_seen_at' => $bestTrackPosition === null ? null : $bestTrackPosition->getLastSeenAt(),
		];
		return $formatted;
	}
	// END Partial loading flow

	/**
	 * @param string $userId
	 * @return Generator
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getAllMapsWithPosition(string $userId): Generator {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$userAccountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');

		// get favorites because liveMapInfo always says favorite: false
		$allFavs = $this->getAllFavorites($userId);
		$allFavsByMapId = [];
		foreach ($allFavs as $fav) {
			$allFavsByMapId[$fav['mapId']] = 1;
		}

		// $pbs = iterator_to_array($this->getMapRecords($userId));
		// $pbs = array_slice($pbs, 0, 100);
		$pbs = $this->getMapRecords($userId);
		$pbTimesByMapId = [];
		$pbByMapId = [];
		foreach ($pbs as $pb) {
			$pbByMapId[$pb['mapId']] = $pb;
			$pbTimesByMapId[$pb['mapId']] = $pb['recordScore']['time'];
		}
		$coreMapInfos = $this->getCoreMapInfo($userId, array_keys($pbTimesByMapId));
		$coreMapInfoByMapUid = [];
		$allMyPbTimesByMapUid = [];
		foreach ($coreMapInfos as $mapInfo) {
			$coreMapInfoByMapUid[$mapInfo['mapUid']] = $mapInfo;
			$time = $pbTimesByMapId[$mapInfo['mapId']];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$mapInfo['mapUid']] = $time;
			}
		}
		$positions = iterator_to_array($this->getScorePositions($userId, $allMyPbTimesByMapUid));

		// prepare the World positions to batch-save
		$positionUpdatesByTrackId = [];
		$formattedMapIds = [];
		foreach ($positions as $position) {
			$mapUid = $position['mapUid'];
			if (isset($coreMapInfoByMapUid[$mapUid])) {
				$mapId = $coreMapInfoByMapUid[$mapUid]['mapId'];
				$formattedMapIds[] = $mapId;
				if (isset($allMyPbTimesByMapUid[$mapUid])) {
					foreach ($position['zones'] as $zone) {
						if ($zone['zoneName'] === 'World') {
							$positionUpdatesByTrackId[$mapId] = [
								'trackUid' => $mapUid,
								'position' => $zone['ranking']['position'],
							];
						}
					}
				}
			}
		}
		if (count($positionUpdatesByTrackId) > 0) {
			$this->trackPositionMapper->updatePositionsOfTracks($userId, $userAccountId, $positionUpdatesByTrackId);
		}
		// best positions are fetched in one query for all maps that get formatted
		$bestPositionsByTrackId = count($formattedMapIds) > 0
			? $this->trackPositionMapper->getLastBestPositionsOfTracks($userId, $userAccountId, $formattedMapIds)
			: [];

		foreach ($positions as $position) {
			$mapUid = $position['mapUid'];
			if (isset($coreMapInfoByMapUid[$mapUid])) {
				$mapId = $coreMapInfoByMapUid[$mapUid]['mapId'];
				$oneResult = [
					'record' => $pbByMapId[$mapId],
				];
				$oneResult['mapInfo'] = $coreMapInfoByMapUid[$mapUid];
				$oneResult['mapInfo']['favorite'] = isset($allFavsByMapId[$mapId]);
				$oneResult['recordPosition'] = isset($allMyPbTimesByMapUid[$mapUid]) ? $position : null;
				yield $this->formatMapResult($userId, $userAccountId, $oneResult, $bestPositionsByTrackId[$mapId] ?? null);
			}
		}

		return [];
	}

	public function formatMapResult(string $userId, string $accountId, array $item, ?TrackPosition $bestTrackPosition): array {
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
		// use the best known position which was fetched beforehand
		$formatted['bestKnownPosition'] = [
			'position' => $bestTrackPosition === null ? null : $bestTrackPosition->getPosition(),
			'first_seen_at' => $bestTrackPosition === null ? null : $bestTrackPosition->getFirstSeenAt(),
			'last_seen_at' => $bestTrackPosition === null ? null : $bestTrackPosition->getLastSeenAt(),
		];
		return $formatted;
	}

	/**
	 * @param string $userId
	 * @param array|null $mapIds
	 * @param array|null $mapUids
	 * @return Generator
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getCoreMapInfo(string $userId, ?array $mapIds = null, ?array $mapUids = null): Generator {
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

		// get cached
		$nonCachedItems = [];
		$nonCachedStringListLength = 0;
		foreach ($itemList as $item) {
			$cacheKey = 'core-map2-' . $cachePrefix . '-' . $item;
			$cachedMapInfo = $this->cache->get($cacheKey);
			if ($cachedMapInfo !== null) {
				// $mapInfos[] = $cachedMapInfo;
				yield $cachedMapInfo;
			} else {
				$nonCachedItems[] = $item;
				$nonCachedStringListLength += strlen($item) + 1;
				if ($nonCachedStringListLength >= 7000) {
					$oneChunk = $this->getCoreMapInfoChunk($userId, $nonCachedItems, $paramName, $itemKeyInMapInfo, $cachePrefix);
					foreach ($oneChunk as $mapInfo) {
						yield $mapInfo;
					}
					$nonCachedItems = [];
					$nonCachedStringListLength = 0;
				}
			}
		}
		// last chunk
		if (count($nonCachedItems) > 0) {
			$oneChunk = $this->getCoreMapInfoChunk($userId, $nonCachedItems, $paramName, $itemKeyInMapInfo, $cachePrefix);
			foreach ($oneChunk as $mapInfo) {
				yield $mapInfo;
			}
		}

		return [];
	}

	/**
	 * @param string $userId
	 * @param array $items
	 * @param string $paramName
	 * @param string $itemKeyInMapInfo
	 * @param string $cachePrefix
	 * @return Generator
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getCoreMapInfoChunk(
		string $userId, array $items, string $paramName, string $itemKeyInMapInfo, string $cachePrefix,
	): Generator {
		$params = [
			$paramName => implode(',', $items),
		];
		// max URI length: 8220 chars
		$endPoint = $paramName === 'mapIdList' ? 'maps/by-id/' : 'maps/by-uid/';
		$oneChunk = $this->request($userId, Application::AUDIENCE_CORE, $endPoint, $params);

		// we yield now
		//if (!isset($oneChunk['error'])) {
		//	$mapInfos = array_merge($mapInfos, $oneChunk);
		//}

		// get map author names
		$authorIds = array_map(function (array $mapInfo) {
			return $mapInfo['author'];
		}, $oneChunk);
		$authorIds = array_unique($authorIds);

		$clientId = $this->appConfig->getValueString(Application::APP_ID, 'client_id');
		$clientSecret = $this->appConfig->getValueString(Application::APP_ID, 'client_secret');
		// only try to get author names if an oauth app is set
		if ($clientId !== '' && $clientSecret !== '') {
			$authorNames = $this->getAuthorNames($authorIds);
			$oneChunk = array_map(
				static function (array $mapInfo) use ($authorNames) {
					$mapInfo['authorName'] = $authorNames[$mapInfo['author']] ?? '???';
					return $mapInfo;
				},
				$oneChunk,
			);
		}

		// cache this chunk
		foreach ($oneChunk as $mapInfo) {
			$cacheKey = 'core-map2-' . $cachePrefix . '-' . $mapInfo[$itemKeyInMapInfo];
			$this->cache->set($cacheKey, $mapInfo, Application::CACHE_DURATION);
		}

		foreach ($oneChunk as $mapInfo) {
			yield $mapInfo;
		}

		return [];
	}

	public function clearMapInfoCache(): void {
		$this->cache->clear('core-map2-');
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

		$fetchedNames = $this->getDisplayNameMap($nonCachedAuthorNames);
		$authorNames = array_merge($authorNames, $fetchedNames);
		// cache the fetched names
		foreach ($fetchedNames as $authorId => $authorName) {
			$cacheKey = 'author-name-' . $authorId;
			$this->cache->set($cacheKey, $authorName, Application::CACHE_DURATION);
		}

		return $authorNames;
	}

	/**
	 * Get the display names of the given accounts
	 *
	 * @param array $accountIds
	 * @return array map of accountId => display name
	 * @throws Exception
	 */
	public function getAccountDisplayNames(array $accountIds): array {
		return $this->getDisplayNameMap($accountIds);
	}

	/**
	 * Get the display names of the given accounts from the OAuth API, max 50 IDs per request
	 *
	 * @param array $accountIds
	 * @return array map of accountId => display name
	 * @throws Exception
	 */
	private function getDisplayNameMap(array $accountIds): array {
		$names = [];
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
		while ($offset < count($accountIds)) {
			$accountIdsToLook = array_slice($accountIds, $offset, $chunkSize);
			$getParamsString = implode(
				'&',
				array_map(
					static function (string $accountId) {
						return 'accountId[]=' . $accountId;
					},
					$accountIdsToLook,
				)
			);
			$response = $this->client->get($url . '?' . $getParamsString, $options);
			$oneChunk = json_decode($response->getBody(), true);
			if (is_array($oneChunk)) {
				$names = array_merge($names, $oneChunk);
			}
			$offset = $offset + $chunkSize;
		}

		return $names;
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
	 * Get the info of the skin equipped by an account.
	 *
	 * @param string $userId
	 * @param string|null $accountId the connected account is used if null
	 * @return array|null an object, or null when the equipped skin could not be retrieved
	 */
	public function getEquippedSkinInfo(string $userId, ?string $accountId = null): ?array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		if ($accountId === null) {
			$accountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		}
		// one request for all requested accounts, users without equipped skin are omitted
		$equippedSkins = $this->request($userId, Application::AUDIENCE_CORE, 'accounts/skins', ['accountIdList' => $accountId]);
		$skinId = null;
		foreach (is_array($equippedSkins) ? $equippedSkins : [] as $equippedSkin) {
			if (isset($equippedSkin['accountId'], $equippedSkin['skinId'])
				&& $equippedSkin['accountId'] === $accountId) {
				$skinId = $equippedSkin['skinId'];
				break;
			}
		}
		if ($skinId === null) {
			// Nadeo omits the account from its equipped skins list when nothing is equipped
			return ['hasSkin' => false];
		}

		$skinInfo = $this->getSkinInfo($userId, $skinId);
		if ($skinInfo === null) {
			return null;
		}
		return [
			'hasSkin' => true,
			'skinId' => $skinId,
			'name' => $skinInfo['skinDisplayName'] ?? '',
			// a preview may exist in the skin ZIP even without a Nadeo thumbnail:
			// let the thumbnail endpoint decide, it degrades to no-preview on failure
			'hasThumbnail' => true,
		];
	}

	/**
	 * Get the info of a skin, cached per skin ID
	 *
	 * @return array|null
	 */
	private function getSkinInfo(string $userId, string $skinId): ?array {
		$cacheKey = 'skin-' . $skinId;
		$skinInfo = $this->cache->get($cacheKey);
		if ($skinInfo === null) {
			$skinInfo = $this->request($userId, Application::AUDIENCE_CORE, 'skins/' . $skinId);
			if (is_array($skinInfo) && isset($skinInfo['skinId'])) {
				$this->cache->set($cacheKey, $skinInfo, self::SKIN_INFO_CACHE_TTL);
			} else {
				return null;
			}
		}
		return is_array($skinInfo) ? $skinInfo : null;
	}

	/**
	 * Get the displayable thumbnail of a skin.
	 * Skins often lack a Nadeo thumbnail (a TGA on their CDN which browsers cannot render),
	 * in that case the skin ZIP itself is searched for a preview image.
	 * The result, failures included, is cached to not probe the same skin repeatedly.
	 *
	 * @param string $userId
	 * @param string $skinId
	 * @return array|null ['body' => string, 'contentType' => string] or null when there is no preview
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getSkinThumbnail(string $userId, string $skinId): ?array {
		$cacheKey = 'skin-image-' . $skinId;
		$cached = $this->cache->get($cacheKey);
		if (is_array($cached)) {
			// failures are cached as an empty body
			return ($cached['body'] ?? '') === '' ? null : $cached;
		}
		$skinInfo = $this->getSkinInfo($userId, $skinId);
		if ($skinInfo === null) {
			return null;
		}
		$thumbnail = $this->getTgaSkinThumbnail($skinInfo['thumbnailUrl'] ?? '');
		if ($thumbnail === null) {
			$thumbnail = $this->getZipSkinThumbnail($skinInfo);
		}
		$cacheable = $thumbnail !== null;
		if ($cacheable) {
			$this->cache->set($cacheKey, $thumbnail, self::SKIN_THUMBNAIL_CACHE_TTL);
		}
		// null means 'not tested, could work later', it is not cached
		return $cacheable ? ($thumbnail['body'] === '' ? null : $thumbnail) : null;
	}

	/**
	 * Fetch the registered Nadeo thumbnail of a skin and convert it to PNG.
	 * Nadeo provides TGA files, browsers cannot render them.
	 *
	 * @param string $thumbnailUrl
	 * @return array|null ['body' => string, 'contentType' => string] or null when there is no registered thumbnail
	 */
	private function getTgaSkinThumbnail(string $thumbnailUrl): ?array {
		if ($thumbnailUrl === '') {
			return null;
		}
		try {
			$image = $this->getImage($thumbnailUrl);
		} catch (ClientException|ServerException $e) {
			$this->logger->warning('Skin thumbnail request failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return null;
		}
		$png = TgaImageConverter::convertToPng((string)($image['body'] ?? ''));
		if ($png === null) {
			$this->logger->warning('Failed to convert skin thumbnail from TGA to PNG', ['app' => Application::APP_ID]);
			return null;
		}
		return ['body' => $png, 'contentType' => 'image/png'];
	}

	/**
	 * Search the skin ZIP for an image which can be used as thumbnail.
	 * Only the ZIP central directory and the selected image are downloaded,
	 * through range requests, Nadeo skin ZIPs can be tens of MBs.
	 *
	 * @param array $skinInfo as returned by getSkinInfo()
	 * @return array|null ['body' => string, 'contentType' => string] or null when nothing usable was found this time.
	 *                    ['body' => '', 'contentType' => ''] when the ZIP was successfully inspected and definitely has no preview
	 */
	private function getZipSkinThumbnail(array $skinInfo): ?array {
		$zipUrls = array_values(array_filter([
			$skinInfo['fileOptimizedUrl'] ?? '',
			$skinInfo['fileUrl'] ?? '',
		]));
		if (count($zipUrls) === 0) {
			return ['body' => '', 'contentType' => ''];
		}
		$emptyResult = ['body' => '', 'contentType' => ''];
		$inspectedWithoutPreview = false;
		$couldHaveMore = false;
		foreach ($zipUrls as $zipUrl) {
			$entries = $this->getZipImageEntries($zipUrl);
			if ($entries === null) {
				// the ZIP could not be inspected, temporarily or not
				$couldHaveMore = true;
				continue;
			}
			$selected = SkinZipPreview::filterPreviewEntries($entries)[0] ?? null;
			if ($selected === null) {
				// the ZIP was fully inspected: it contains no usable preview image,
				// but its other version may still have one
				$inspectedWithoutPreview = true;
				continue;
			}
			$image = $this->extractZipEntryImage($zipUrl, $selected);
			if ($image === null) {
				$couldHaveMore = true;
				continue;
			}
			return $image;
		}
		// every candidate ZIP got inspected and none had a usable image
		return ($inspectedWithoutPreview && !$couldHaveMore) ? $emptyResult : null;
	}

	/**
	 * List the image entries of a remote ZIP file.
	 *
	 * @param string $zipUrl
	 * @return array|null entry list like SkinZipPreview::listEntries(), null when the file could not be read
	 */
	private function getZipImageEntries(string $zipUrl): ?array {
		// one-byte probe: gets the file size from Content-Range or, when a server ignores ranges,
		// the whole body to avoid more downloads
		$probe = $this->fetchZipRange($zipUrl, '0-0');
		if ($probe === null) {
			return null;
		}
		$whole = '';
		if ($probe['status'] === 206 && preg_match('/\/(\d+)$/', $probe['contentRange'], $m)) {
			$total = (int)$m[1];
		} else {
			// the range was ignored, the whole file is already here
			$whole = $probe['body'];
			$total = strlen($whole);
		}
		if ($total === 0) {
			return null;
		}

		// the end of central directory record sits in the last bytes of the file
		$tailLength = min($total, self::ZIP_EOCD_SEARCH_SIZE);
		$tail = ($whole !== '')
			? substr($whole, $total - $tailLength)
			: ($this->fetchZipSlice($zipUrl, $total - $tailLength, $total - 1) ?? '');
		$eocd = SkinZipPreview::parseEOCD($tail);
		if ($eocd === null) {
			return null;
		}

		$centralDirectory = ($whole !== '')
			? substr($whole, $eocd['cdOffset'], $eocd['cdSize'])
			: ($this->fetchZipSlice($zipUrl, $eocd['cdOffset'], $eocd['cdOffset'] + $eocd['cdSize'] - 1) ?? '');
		if ($centralDirectory === '' || strlen($centralDirectory) !== $eocd['cdSize']) {
			return null;
		}
		return SkinZipPreview::listEntries($centralDirectory);
	}

	/**
	 * Download and decompress one ZIP entry.
	 *
	 * @param string $zipUrl
	 * @param array $entry like SkinZipPreview::listEntries() items
	 * @return array|null ['body' => string, 'contentType' => string] or null when the entry cannot be turned into an image
	 */
	private function extractZipEntryImage(string $zipUrl, array $entry): ?array {
		// a small margin first: some local headers have an extra field longer than the central one
		$data = $this->fetchZipSlice($zipUrl, $entry['localHeaderOffset'], $entry['localHeaderOffset'] + 30 + strlen($entry['name']) + 4095);
		if ($data === null) {
			return null;
		}
		$dataOffset = SkinZipPreview::localDataOffset(substr($data, 0, 30));
		if ($dataOffset === null) {
			return null;
		}
		$neededLength = $dataOffset + $entry['compressedSize'];
		if (strlen($data) < $neededLength) {
			$more = $this->fetchZipSlice($zipUrl, $entry['localHeaderOffset'] + strlen($data), $entry['localHeaderOffset'] + $neededLength - 1);
			if ($more === null) {
				return null;
			}
			$data .= $more;
		}
		$compressed = substr($data, $dataOffset, $entry['compressedSize']);
		if (strlen($compressed) < $entry['compressedSize']) {
			return null;
		}
		$image = SkinZipPreview::decodeEntry($compressed, $entry['method'], $entry['uncompressedSize']);
		if ($image === null) {
			$this->logger->warning('Failed to decompress skin preview entry ' . $entry['name'], ['app' => Application::APP_ID]);
			return null;
		}
		$extension = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
		if ($extension === 'png') {
			return ['body' => $image, 'contentType' => 'image/png'];
		}
		if ($extension === 'jpg' || $extension === 'jpeg') {
			return ['body' => $image, 'contentType' => 'image/jpeg'];
		}
		$png = TgaImageConverter::convertToPng($image);
		if ($png === null) {
			$this->logger->warning('Failed to convert skin preview from TGA to PNG', ['app' => Application::APP_ID]);
			return null;
		}
		return ['body' => $png, 'contentType' => 'image/png'];
	}

	/**
	 * @param string $url
	 * @param string $range value of the HTTP Range header, e.g. '0-0'
	 * @return array|null ['status' => int, 'body' => string, 'contentRange' => string] or null on network error
	 */
	private function fetchZipRange(string $url, string $range): ?array {
		try {
			$response = $this->client->get($url, [
				'headers' => [
					'Range' => 'bytes=' . $range,
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				],
				'timeout' => 60,
			]);
		} catch (Exception $e) {
			$this->logger->warning('Skin ZIP range request failed: ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return null;
		}
		return [
			'status' => $response->getStatusCode(),
			'body' => (string)$response->getBody(),
			'contentRange' => $response->getHeader('Content-Range'),
		];
	}

	/**
	 * Get the raw bytes of an absolute byte range of a remote file.
	 * Servers which ignore range requests make this cheaper: they send the whole file, sliced here.
	 *
	 * @param string $url
	 * @param int $from
	 * @param int $to inclusive
	 * @return string|null the bytes between $from and $to, null on network error
	 */
	private function fetchZipSlice(string $url, int $from, int $to): ?string {
		$response = $this->fetchZipRange($url, $from . '-' . $to);
		if ($response === null) {
			return null;
		}
		if ($response['status'] === 206) {
			return $response['body'];
		}
		return strlen($response['body']) > $from ? substr($response['body'], $from, $to - $from + 1) : null;
	}

	/**
	 * Get the records of the given accounts on the given maps.
	 * The connected account is used if $accountIds is null: in that case all its records
	 * are retrieved and $mapIds is ignored, the full endpoint is faster than one request per map.
	 *
	 * @param string $userId
	 * @param array|null $accountIds
	 * @param array|null $mapIds
	 * @return Generator
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapRecords(string $userId, ?array $accountIds = null, ?array $mapIds = null): Generator {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];

		$userAccountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		if ($accountIds === null) {
			$offset = 0;
			do {
				$newRecords = $this->getMapRecordsPage($userId, $userAccountId, $offset);
				foreach ($newRecords as $record) {
					yield $record;
				}
				$offset += self::OWN_RECORDS_PAGE_SIZE;
			} while (count($newRecords) === self::OWN_RECORDS_PAGE_SIZE);
			return [];
		}

		// we have a map list and a list of accounts
		// we get all records of those maps for all accounts
		yield from $this->getRecordsByAccount($userId, $userAccountId, $accountIds, $mapIds);

		return [];
	}

	/**
	 * Get the records of the given accounts on the given maps.
	 * The endpoint only accepts one map per request, so uncached maps are fetched
	 * with a bounded pool of parallel requests.
	 * When asking for a single other account, results are cached per (account, map).
	 * Records of the connected account itself are never cached.
	 *
	 * @param string $userId
	 * @param string $userAccountId
	 * @param array $accountIds
	 * @param array $mapIds
	 * @return Generator
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	private function getRecordsByAccount(string $userId, string $userAccountId, array $accountIds, array $mapIds): Generator {
		$recordsByMapId = [];
		$cacheable = count($accountIds) === 1 && $accountIds[0] !== $userAccountId;

		// get cached
		$nonCachedMapIds = [];
		foreach ($mapIds as $mapId) {
			if ($cacheable) {
				$cacheKey = 'map-record-' . $accountIds[0] . '-' . $mapId;
				$cachedRecords = $this->cache->get($cacheKey);
				if ($cachedRecords !== null) {
					$recordsByMapId[$mapId] = $cachedRecords;
					continue;
				}
			}
			$nonCachedMapIds[] = $mapId;
		}

		$params = [
			'accountIdList' => implode(',', $accountIds),
		];
		if (count($nonCachedMapIds) > 0) {
			$accessToken = $this->getCheckedToken($userId, Application::AUDIENCE_CORE);
			$options = [
				'headers' => [
					'Authorization' => 'nadeo_v1 t=' . $accessToken,
					'Content-Type' => 'application/json',
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				],
				'timeout' => 120,
			];
			for ($offset = 0; $offset < count($nonCachedMapIds); $offset += self::CONCURRENT_RECORD_REQUESTS) {
				$batchMapIds = array_slice($nonCachedMapIds, $offset, self::CONCURRENT_RECORD_REQUESTS);
				$promises = [];
				foreach ($batchMapIds as $mapId) {
					$url = $this->buildRequestUrl(
						Application::AUDIENCES[Application::AUDIENCE_CORE]['base_url'],
						'v2/mapRecords/by-account/',
						array_merge($params, ['mapId' => $mapId]),
						'GET',
					);
					$promises[$mapId] = $this->client->getAsync($url, $options);
				}
				// waiting on one promise drives the shared curl multi handler
				// and also progresses the other requests in flight
				foreach ($batchMapIds as $mapId) {
					try {
						$response = $promises[$mapId]->wait(true);
					} catch (ClientException|ServerException $e) {
						throw new TmApiRequestException($e, Application::AUDIENCE_CORE, 'v2/mapRecords/by-account/', 'GET', $params);
					}
					$records = json_decode((string)$response->getBody(), true);
					$recordsByMapId[$mapId] = is_array($records) ? $records : [];
				}
			}
			if ($cacheable) {
				foreach ($nonCachedMapIds as $mapId) {
					$this->cache->set('map-record-' . $accountIds[0] . '-' . $mapId, $recordsByMapId[$mapId], self::MAP_RECORD_CACHE_TTL);
				}
			}
		}

		foreach ($mapIds as $mapId) {
			foreach ($recordsByMapId[$mapId] ?? [] as $record) {
				yield $record;
			}
		}

		return [];
	}

	public function getScorePositions(string $userId, array $scoresByMapUid): Generator {
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
					yield $position;
				}
			}
			$offset = $offset + $chunkSize;
		}

		return [];
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
	 * @param int $offset
	 * @return array|null list of leaderboard rows, null on error
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	private function getMapTopRows(string $userId, string $mapUid, int $offset): ?array {
		$top = $this->getMapTop($userId, $mapUid, $offset, self::RANK_PAGE_SIZE);
		if (isset($top['error']) || !isset($top['tops'][0]['top']) || !is_array($top['tops'][0]['top'])) {
			return null;
		}
		return $top['tops'][0]['top'];
	}

	/**
	 * Exact rank that a score would reach on the world leaderboard of a map.
	 * Unlike the position endpoint, this works for scores of any value by
	 * scrolling the real leaderboard pages: the position endpoint cannot rank
	 * scores worse than the authenticated player's own record.
	 *
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $score
	 * @return array ['position' => int, 'beyondPosition' => null] or ['position' => null, 'beyondPosition' => int]
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	public function getMapScoreRank(string $userId, string $mapUid, int $score): array {
		$cacheKey = 'rank-' . $mapUid . '-' . $score;
		$cached = $this->cache->get($cacheKey);
		if (is_array($cached)) {
			return $cached;
		}

		$pageSize = self::RANK_PAGE_SIZE;
		// scoring board rows are sorted by position with ascending scores,
		// the score lands on the first page that holds a row of a worse-or-equal score.
		// find that page by galloping through the page boundaries, then bisecting
		$dataLow = -1;          // last page known to hold no row with a score >= $score
		$dataHigh = null;       // first page known to hold such a row
		$visibleMaxPosition = null;
		$pageIndex = 0;
		$step = 1;
		while (true) {
			$pagePosition = $pageIndex * $pageSize;
			$rows = $this->getMapTopRows($userId, $mapUid, $pagePosition);
			// a row position different from the offset means the leaderboard is capped:
			// the requested score is worse than every visible entry
			if ($rows === null
				|| count($rows) === 0
				|| (($rows[0]['position'] ?? null) !== $pagePosition + 1)) {
				break;
			}
			$visibleMaxPosition = $rows[count($rows) - 1]['position'] ?? $visibleMaxPosition;
			if (($rows[count($rows) - 1]['score'] ?? 0) >= $score) {
				$dataHigh = $pageIndex;
				break;
			}
			$dataLow = $pageIndex;
			$pageIndex += $step;
			$step *= 2;
		}

		if ($dataHigh !== null) {
			while ($dataHigh > $dataLow + 1) {
				$middle = intdiv($dataLow + $dataHigh, 2);
				$pagePosition = $middle * $pageSize;
				$rows = $this->getMapTopRows($userId, $mapUid, $pagePosition);
				if ($rows === null || count($rows) === 0 || (($rows[0]['position'] ?? null) !== $pagePosition + 1)) {
					// the leaderboard shrank while probing, treat as capped
					$dataHigh = null;
					break;
				}
				$visibleMaxPosition = $rows[count($rows) - 1]['position'] ?? $visibleMaxPosition;
				if (($rows[count($rows) - 1]['score'] ?? 0) >= $score) {
					$dataHigh = $middle;
				} else {
					$dataLow = $middle;
				}
			}

			if ($dataHigh !== null) {
				$rows = $this->getMapTopRows($userId, $mapUid, $dataHigh * $pageSize) ?? [];
				foreach ($rows as $row) {
					if (($row['score'] ?? 0) >= $score) {
						return $this->cacheRank($cacheKey, $row['position'], null);
					}
				}
			}
		}
		return $this->cacheRank($cacheKey, null, $visibleMaxPosition);
	}

	/**
	 * @param string $cacheKey
	 * @param int|null $position
	 * @param int|null $beyondPosition
	 * @return array
	 */
	private function cacheRank(string $cacheKey, ?int $position, ?int $beyondPosition): array {
		$result = [
			'position' => $position,
			'beyondPosition' => $beyondPosition,
		];
		$this->cache->set($cacheKey, $result, self::RANK_CACHE_TTL);
		return $result;
	}

	/**
	 * Get the world record of each given map.
	 * One request per map through a bounded pool of concurrent requests, tokens checked once.
	 * No caching is involved, records are fetched on each call.
	 *
	 * @param string $userId
	 * @param array $mapUids
	 * @return array map of mapUid => ['time', 'account_id', 'unix_timestamp'] or ['time' => null] when there is no record
	 * @throws PreConditionNotMetException
	 * @throws TmApiRequestException
	 * @throws TokenRefreshException
	 */
	private function getWorldRecords(string $userId, array $mapUids): array {
		if (count($mapUids) === 0) {
			return [];
		}
		$accessToken = $this->getCheckedToken($userId, Application::AUDIENCE_LIVE);
		$options = [
			'headers' => [
				'Authorization' => 'nadeo_v1 t=' . $accessToken,
				'Content-Type' => 'application/json',
				'User-Agent' => Application::INTEGRATION_USER_AGENT,
			],
			'timeout' => 120,
		];

		$worldRecordsByMapUid = [];
		for ($offset = 0; $offset < count($mapUids); $offset += self::CONCURRENT_RECORD_REQUESTS) {
			$batchMapUids = array_slice($mapUids, $offset, self::CONCURRENT_RECORD_REQUESTS);
			$promises = [];
			foreach ($batchMapUids as $mapUid) {
				$url = $this->buildRequestUrl(
					Application::AUDIENCES[Application::AUDIENCE_LIVE]['base_url'],
					'leaderboard/group/Personal_Best/map/' . $mapUid . '/top',
					['onlyWorld' => 'true', 'length' => 1],
					'GET',
				);
				$promises[$mapUid] = $this->client->getAsync($url, $options);
			}
			// waiting on one promise drives the shared curl multi handler
			// and also progresses the other requests in flight
			foreach ($batchMapUids as $mapUid) {
				$worldRecordsByMapUid[$mapUid] = [
					'time' => null,
					'account_id' => null,
					'unix_timestamp' => null,
				];
				try {
					$response = $promises[$mapUid]->wait(true);
				} catch (ClientException|ServerException $e) {
					$this->logger->warning('Failed to get world record of map ' . $mapUid, ['app' => Application::APP_ID, 'exception' => $e]);
					continue;
				}
				$tops = json_decode((string)$response->getBody(), true);
				$wr = $tops['tops'][0]['top'][0] ?? null;
				if (is_array($wr) && isset($wr['score']) && is_numeric($wr['score']) && $wr['score'] > -1) {
					$worldRecordsByMapUid[$mapUid] = [
						'time' => (int)$wr['score'],
						'account_id' => $wr['accountId'] ?? null,
						'unix_timestamp' => isset($wr['timestamp']) ? (int)$wr['timestamp'] : null,
					];
				}
			}
		}

		// WR holder names are resolved like map author names, batched and cached
		$wrAccountIds = array_values(array_filter(array_column($worldRecordsByMapUid, 'account_id')));
		$wrNames = $this->getAuthorNames($wrAccountIds);
		foreach ($worldRecordsByMapUid as &$worldRecord) {
			if ($worldRecord['account_id'] !== null) {
				$worldRecord['name'] = $wrNames[$worldRecord['account_id']] ?? '';
			}
		}
		return $worldRecordsByMapUid;
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
	 * @param string $accountId
	 * @return array|null null when the account is unknown to trackmania.io
	 */
	private function getAccountInfo(string $accountId): ?array {
		$cacheKey = 'tmio-player-' . $accountId;
		$cachedPlayer = $this->cache->get($cacheKey);
		if ($cachedPlayer !== null) {
			return is_array($cachedPlayer) ? $cachedPlayer : null;
		}
		$player = $this->requestTrackmaniaIo('player/' . $accountId);
		if (!is_array($player) || isset($player['error']) || !isset($player['accountid'])) {
			return null;
		}
		$this->cache->set($cacheKey, $player, Application::CACHE_DURATION);
		return $player;
	}

	/**
	 * Get the zone info of an account from trackmania.io, null when the account has none.
	 * The flag code is the one of the country level, just above the regions, e.g. FRA for France.
	 *
	 * @param string $accountId
	 * @return array|null ['flag_code' => string, 'zone_name' => string, 'display_name' => string]
	 */
	public function getZoneInfo(string $accountId): ?array {
		$player = $this->getAccountInfo($accountId);
		if ($player === null) {
			return null;
		}
		// the zone can be located at the root of the player or in its trophies
		$zone = $player['zone'] ?? $player['trophies']['zone'] ?? null;
		if (!is_array($zone)) {
			return null;
		}
		$zoneNames = [$zone['name']];
		$flags = [$zone['flag']];
		while (isset($zone['parent'])) {
			$zone = $zone['parent'];
			$zoneNames[] = $zone['name'];
			$flags[] = $zone['flag'];
		}
		$flag = 'WOR';
		if (count($flags) > 2) {
			$flag = $flags[count($flags) - 3];
		} elseif (count($flags) > 1) {
			$flag = $flags[count($flags) - 2];
		}
		return [
			'flag_code' => $flag,
			'zone_name' => implode(', ', $zoneNames),
			'display_name' => $player['displayname'] ?? '',
		];
	}

	/**
	 * Refresh the stored zone info of a connected user when it is incomplete.
	 * A missing flag or display name can happen after connecting with a service account.
	 *
	 * @param string $userId
	 * @return void
	 */
	public function refreshStoredZoneInfo(string $userId): void {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$connected = $this->secretService->getEncryptedUserValue($userId, $prefix . 'token') !== '';
		if (!$connected) {
			return;
		}
		$accountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		if ($accountId === '') {
			return;
		}
		$isIncomplete = false;
		foreach (['user_flag_code', 'user_zone_name', 'user_display_name'] as $key) {
			if ($this->config->getUserValue($userId, Application::APP_ID, $key) === '') {
				$isIncomplete = true;
				break;
			}
		}
		if (!$isIncomplete) {
			return;
		}
		$zoneInfo = $this->getZoneInfo($accountId);
		if ($zoneInfo === null) {
			return;
		}
		$this->config->setUserValue($userId, Application::APP_ID, 'user_flag_code', $zoneInfo['flag_code']);
		$this->config->setUserValue($userId, Application::APP_ID, 'user_zone_name', $zoneInfo['zone_name']);
		$this->config->setUserValue($userId, Application::APP_ID, 'user_display_name', $zoneInfo['display_name']);
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
	 * Build the full request url, converting array parameters into query parameters
	 *
	 * @param string $baseUrl
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return string
	 */
	private function buildRequestUrl(string $baseUrl, string $endPoint, array $params, string $method): string {
		$url = $baseUrl . $endPoint;
		if (count($params) > 0 && $method === 'GET') {
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
		}
		return $url;
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
		$accessToken = $this->getCheckedToken($userId, $audience);
		try {
			$url = $this->buildRequestUrl(Application::AUDIENCES[$audience]['base_url'], $endPoint, $params, $method);
			$options = [
				'headers' => [
					'Authorization' => 'nadeo_v1 t=' . $accessToken,
					'Content-Type' => 'application/json',
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
				],
				'timeout' => 120,
			];

			if (count($params) > 0 && $method !== 'GET') {
				$options['json'] = $params;
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
		} catch (BadResponseException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('API error Client/Server exception: ' . $body, ['exception' => $e]);
			throw new TmApiRequestException($e, $audience, $endPoint, $method, $params);
		}
	}

	/**
	 * Refresh the token if needed and return the current access token
	 *
	 * @param string $userId
	 * @param string $audience
	 * @return string
	 * @throws PreConditionNotMetException
	 * @throws TokenRefreshException
	 */
	private function getCheckedToken(string $userId, string $audience): string {
		$this->checkTokenExpiration($userId, $audience);
		return $this->secretService->getEncryptedUserValue($userId, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token');
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
	 * New login method
	 * see https://webservices.openplanet.dev/auth/service
	 *
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function login(string $login, string $password): array {
		try {
			$result = [];
			foreach (Application::AUDIENCES as $audienceKey => $v) {
				$tokens = $this->getAccessTokenFromBasicAuth($login, $password, $audienceKey);
				if (isset($tokens['accessToken'], $tokens['refreshToken'])) {
					$result[$audienceKey] = $tokens;
				}
			}
			if (isset($result[Application::AUDIENCE_CORE]['accessToken'])) {
				$decodedToken = ConfigController::decodeToken($result[Application::AUDIENCE_CORE]['accessToken']);
				$result['userId'] = $decodedToken['sub'];
				$result['nameOnPlatform'] = $decodedToken['aun'];
			}
			if (isset($result['userId'], $result['nameOnPlatform'])) {
				return $result;
			}
			return ['error' => $this->l10n->t('Error during login')];
		} catch (Exception $e) {
			$this->logger->warning('login error : ' . $e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $login
	 * @param string $password
	 * @param string $audience
	 * @return array
	 */
	public function getAccessTokenFromBasicAuth(string $login, string $password, string $audience): array {
		try {
			$url = 'https://prod.trackmania.core.nadeo.online/v2/authentication/token/basic';
			$options = [
				'headers' => [
					'User-Agent' => Application::INTEGRATION_USER_AGENT,
					'Content-Type' => 'application/json',
					'Authorization' => 'Basic ' . base64_encode($login . ':' . $password),
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

	public function updatePositionsOfConnectedUsers(): Generator {
		$userIds = $this->trackPositionMapper->getConnectedUserIds();
		foreach ($userIds as $userId) {
			if (!$this->isUserConnected($userId)) {
				continue;
			}
			try {
				error_log('[TM] Starting user ' . $userId);
				foreach ($this->getAllMapsWithPosition($userId) as $item) {
					yield ['user_id' => $userId, 'map' => $item];
				};
				error_log('[TM] End user ' . $userId);
			} catch (Exception|Throwable) {
			}
		}

		return [];
	}
}
