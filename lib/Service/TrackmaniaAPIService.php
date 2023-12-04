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

use Datetime;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Controller\ConfigController;
use OCP\Http\Client\IClient;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;
use Throwable;

class TrackmaniaAPIService {

	private IClient $client;
	private ICache $cache;

	public function __construct(
		string $appName,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		ICacheFactory $cacheFactory,
		IClientService $clientService
	) {
		$this->client = $clientService->newClient();
		$this->cache = $cacheFactory->createDistributed(Application::APP_ID);
	}

	public function getImage(string $url): array {
		$response = $this->client->get($url);
		return [
			'body' => $response->getBody(),
			'headers' => $response->getHeaders(),
		];
	}

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
		$allMyPbTimesByMapUid = [];
		foreach ($coreMapInfos as $mapInfo) {
			$time = $pbTimesByMapId[$mapInfo['mapId']];
			if ($time !== null) {
				$allMyPbTimesByMapUid[$mapInfo['mapUid']] = $time;
			}
		}
		// there is more information in the live endpoint
		$liveMapInfos = $this->getLiveMapInfo($userId, array_keys($allMyPbTimesByMapUid));
		$liveMapInfoByMapId = [];
		foreach ($liveMapInfos as $mapInfo) {
			$liveMapInfoByMapId[$mapInfo['mapId']] = $mapInfo;
		}
		$positionsByMapUid = $this->getScorePositions($userId, $allMyPbTimesByMapUid);
		$results = [];
		foreach ($pbs as $k => $pb) {
			$oneResult = [
				'record' => $pb,
			];
			$mapId = $pb['mapId'];
			if (isset($liveMapInfoByMapId[$mapId])) {
				$mapUid = $liveMapInfoByMapId[$mapId]['uid'];
				$oneResult['mapInfo'] = $liveMapInfoByMapId[$mapId];
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
		return array_map(static function(array $item) {
			$formatted = [
				'record' => [
					'accountId' => $item['record']['accountId'],
					'medal' => $item['record']['medal'],
					'recordScore' => $item['record']['recordScore'],
					'unix_timestamp' => (new DateTime($item['record']['timestamp']))->getTimestamp(),
				],
				'mapInfo' => [
					'uid' => $item['mapInfo']['uid'],
					'mapId' => $item['mapInfo']['mapId'],
					'name' => $item['mapInfo']['name'],
					'favorite' => $item['mapInfo']['favorite'],
					'authorTime' => $item['mapInfo']['authorTime'],
					'goldTime' => $item['mapInfo']['goldTime'],
					'silverTime' => $item['mapInfo']['silverTime'],
					'bronzeTime' => $item['mapInfo']['bronzeTime'],
					'thumbnailUrl' => $item['mapInfo']['thumbnailUrl'],
				],
				'recordPosition' => [
					'score' => $item['recordPosition']['score'],
					'zones' => [],
				],
			];
			foreach ($item['recordPosition']['zones'] as $zone) {
//				$formatted['recordPosition']['zones'][$zone['zoneName']] = $zone;
				$formatted['recordPosition']['zones'][$zone['zoneName']] = $zone['ranking']['position'];
			}
			return $formatted;
		}, $data);
	}

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
		return $mapInfos;
	}

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
			While (count($maps) < $nbMaps || isset($favs['error'])) {
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
	 * @param array|null $accountIds connected account is used if null
	 * @param array|null $mapIds all records are retrieved if null (only works with the connected account)
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getMapRecords(string $userId, ?array $accountIds = null, ?array $mapIds = null): array {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$accountIdList = $accountIds === null
			? $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id')
			: implode(',', $accountIds);
		$params = [
			'accountIdList' => $accountIdList,
//			'seasonId' => '???',
		];
		if ($mapIds !== null) {
			$params['mapIdList'] = implode(',', $mapIds);
		}

		// max URI length: 8220 chars
		return $this->request($userId, Application::AUDIENCE_CORE, 'mapRecords/', $params);
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
			$getParams = array_map(function($uid) use ($scoresByMapUid) {
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
		return $this->request($userId, Application::AUDIENCE_LIVE, 'leaderboard/group/map?scores['.$mapUid.']='.$score, $params, 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @param int $offset
	 * @param int $length
	 * @param bool $onlyWorld
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function getMapTop(string $userId, string $mapUid, int $offset = 0, int $length = 20, bool $onlyWorld = true): array	{
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
		$url = 'https://map-monitor.xk.io/map/' . $mapUid . '/nb_players/refresh';
		try {
			$response = $this->client->get($url);
			$body = $response->getBody();
			$parsedBody = json_decode($body, true);
			if (is_array($parsedBody) && isset($parsedBody['nb_players']) && is_numeric($parsedBody['nb_players'])) {
				return $parsedBody['nb_players'];
			}
		} catch (Exception | Throwable $e) {
			$this->logger->warning('Failed to get nb players from map-monitor.xk.io', ['app' => Application::APP_ID, 'exception' => $e]);
		}
		return null;
	}

	/**
	 * @param string $userId
	 * @param string $mapUid
	 * @return int
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
	 * @return array|null
	 */
	public function getMyPositionFromTop(string $userId, string $mapUid): ?array {
		$chunkSize = 100;
		$prefix = Application::AUDIENCES[Application::AUDIENCE_LIVE]['token_config_key_prefix'];
		$accountId = $this->config->getUserValue($userId, Application::APP_ID, $prefix . 'account_id');
		$pos = null;
		$offset = 0;
		while ($pos === null && $offset < 10000) {
			error_log('getMyPosition[' . $offset . ']');
			$top = $this->getMapTop($userId, $mapUid, $offset, $chunkSize);
			if (isset($top['error'])) {
				return null;
			}
			$pos = $this->findMyPosition($accountId, $top);
			$offset = $offset + $chunkSize;
		}
		return $pos;
	}

	public function findMyPosition(string $accountId, array $top): ?array {
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
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @param bool $jsonResponse
	 * @return array|mixed|resource|string|string[]
	 * @throws PreConditionNotMetException
	 */
	public function request(
		string $userId, string $audience, string $endPoint, array $params = [], string $method = 'GET', bool $jsonResponse = true
	) {
		$this->checkTokenExpiration($userId, $audience);
		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token');
		try {
			$url = Application::AUDIENCES[$audience]['base_url'] . $endPoint;
			$options = [
				'headers' => [
					'Authorization'  => 'nadeo_v1 t=' . $accessToken,
					'Content-Type' => 'application/json',
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
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
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
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
		} catch (ServerException | ClientException $e) {
			$body = $e->getResponse()->getBody();
			$this->logger->warning('API error : ' . $body, ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		} catch (Exception | Throwable $e) {
			$this->logger->warning('API error', ['exception' => $e, 'app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function checkTokenExpiration(string $userId, string $audience): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId, $audience);
			}
		}
	}

	/**
	 * @param string $userId
	 * @return bool
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function refreshToken(string $userId, string $audience): bool {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No ' . $audience . ' refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		try {
			$url = Application::TOKEN_REFRESH_URL;
			$options = [
				'headers' => [
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
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
					$this->config->setUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'token', $accessToken);
					$this->config->setUserValue($userId, Application::APP_ID, Application::AUDIENCES[$audience]['token_config_key_prefix'] . 'refresh_token', $refreshToken);

					$prefix = Application::AUDIENCES[$audience]['token_config_key_prefix'];
					$decodedToken = ConfigController::decodeToken($accessToken);
					$expiresAt = $decodedToken['exp'];
					$this->config->setUserValue($userId, Application::APP_ID, $prefix . 'token_expires_at', $expiresAt);
					return true;
				}
				return false;
			}
		} catch (Exception $e) {
			error_log('refresh exception '.$e->getMessage());
			$this->logger->error(
				$audience . ' token is not valid anymore. Impossible to refresh it. '
				. $result['error'] . ' '
				. $result['error_description'] ?? '[no error description]',
				['app' => Application::APP_ID]
			);
			return false;
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
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
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
			$this->logger->warning('login error : '.$e->getMessage(), ['app' => Application::APP_ID]);
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
					'User-Agent'  => Application::INTEGRATION_USER_AGENT,
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
			$this->logger->warning('login error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}
}
