<?php
/**
 * Nextcloud - Trackmania
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Trackmania\Controller;

use Exception;
use GuzzleHttp\Exception\ClientException;
use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Exception\TmApiRequestException;
use OCA\Trackmania\Exception\TokenRefreshException;
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IRequest;

use OCP\IURLGenerator;

class TrackmaniaAPIController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private IURLGenerator $urlGenerator,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function test(): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->test($this->userId);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * Does not work for the moment
	 *
	 * @param string $accountId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAccountInfo(string $accountId): DataResponse {
		$params = [
			'accountIdList' => $accountId,
		];
		try {
			$result = $this->trackmaniaAPIService->request($this->userId, Application::AUDIENCE_CORE, 'accounts/displayNames/', $params);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $name
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function searchAccount(string $name): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->searchAccount($name);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getFlag(string $code): DataDisplayResponse {
		$url = 'https://trackmania.io/img/flags/' . $code . '.jpg';
		$image = $this->trackmaniaAPIService->getImage($url);
		if (isset($image['body'], $image['headers'])) {
			$response = new DataDisplayResponse(
				$image['body'],
				Http::STATUS_OK,
				['Content-Type' => $image['headers']['Content-Type'][0] ?? 'image/jpeg']
			);
			$response->cacheFor(60 * 60 * 24, false, true);
			return $response;
		}
		return new DataDisplayResponse('', Http::STATUS_NOT_FOUND);
	}

	private function getExceptionResponse(TokenRefreshException|TmApiRequestException $e): DataResponse {
		$requestException = $e->requestException;
		$tmResponse = $requestException->getResponse();
		$statusCode = $tmResponse->getStatusCode();
		$body = $tmResponse->getBody();
		$parsedResponse = json_decode($body, true);
		$result = [
			'status_code' => $statusCode,
			'response' => $parsedResponse,
		];
		if ($e instanceof TokenRefreshException) {
			$result['error'] = 'token_refresh_failed';
		} else {
			$result['exception_class'] = get_class($requestException);
			$result['error'] = 'tm_api_request_failed';
			$result['audience'] = $e->audience;
			$result['endpoint'] = $e->endpoint;
			$result['method'] = $e->method;
			$result['params'] = $e->params;
		}
		return new DataResponse($result, $statusCode);
	}

	/**
	 * @param array|null $mapIdList List of map IDs to avoid getting all maps played by current account
	 * @return DataResponse
	 * @throws AppConfigTypeConflictException
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMyRawRecords(?array $mapIdList = null): DataResponse {
		// get a token once to avoid trying to get multiple new ones simultaneously in /pbs/info
		$this->trackmaniaAPIService->getOAuthToken();
		try {
			$result = $this->trackmaniaAPIService->getMapRecordsAndFavorites($this->userId, $mapIdList);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param array $pbTimesByMapId
	 * @param string|null $otherAccountId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMoreRecordInfo(array $pbTimesByMapId, ?string $otherAccountId = null):DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getMapsInfoAndRecordPositions($this->userId, $pbTimesByMapId, $otherAccountId);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
			//		} catch (\Throwable $e) {
			//			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMyRecords():DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getAllMapsWithPosition($this->userId);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapId
	 * @param string $fallbackName
	 * @return Response
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapThumbnail(string $mapId, string $fallbackName = '?'): Response {
		try {
			$mapInfo = $this->trackmaniaAPIService->getCoreMapInfo($this->userId, [$mapId]);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		$mapInfo = iterator_to_array($mapInfo);
		if (count($mapInfo) === 0) {
			return new DataResponse('Map info not found', Http::STATUS_NOT_FOUND);
		}
		$url = $mapInfo[0]['thumbnailUrl'];
		try {
			$image = $this->trackmaniaAPIService->getImage($url);
			if (isset($image['body'], $image['headers'])) {
				$response = new DataDisplayResponse(
					$image['body'],
					Http::STATUS_OK,
					['Content-Type' => $image['headers']['Content-Type'][0] ?? 'image/jpeg']
				);
				$response->cacheFor(60 * 60 * 24, false, true);
				return $response;
			}

			$fallbackAvatarUrl = $this->urlGenerator->linkToRouteAbsolute('core.GuestAvatar.getAvatar', ['guestName' => $fallbackName, 'size' => 200]);
			return new RedirectResponse($fallbackAvatarUrl);
		} catch (ClientException $e) {
			return new DataResponse($e->getMessage(), $e->getResponse()->getStatusCode());
		}
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getLiveMapInfo(string $mapUid): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getLiveMapInfo($this->userId, [$mapUid]);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCoreMapInfoById(string $mapId): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getCoreMapInfo($this->userId, [$mapId]);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCoreMapInfoByUid(string $mapUid): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getCoreMapInfo($this->userId, null, [$mapUid]);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapUid
	 * @param int $offset
	 * @param int $length
	 * @param bool $onlyWorld
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTop(string $mapUid, int $offset = 0, int $length = 100, bool $onlyWorld = true): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getMapTop($this->userId, $mapUid, $offset, $length, $onlyWorld);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapFinishCount(string $mapUid): DataResponse {
		try {
			$finishCount = $this->trackmaniaAPIService->getMapFinishCount($this->userId, $mapUid);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($finishCount);
	}

	/**
	 * @param string $mapUid
	 * @param int $time
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTimePosition(string $mapUid, int $time): DataResponse {
		try {
			$scorePosition = $this->trackmaniaAPIService->getScorePosition($this->userId, $mapUid, $time);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($scorePosition);
	}

	/**
	 * @param string $mapUid
	 * @param int $time
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTimeImprovements(string $mapUid, int $time): DataResponse {
		try {
			$scoreImprovements = $this->trackmaniaAPIService->getScoreImprovements($this->userId, $mapUid, $time);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($scoreImprovements);
	}

	/**
	 * @param string $mapUid
	 * @param string $maction
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function toggleFavorite(string $mapUid, string $maction): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->toggleFavorite($this->userId, $mapUid, $maction);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $mapId
	 * @param string $accountId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getAccountRecordOnMap(string $mapId, string $accountId): DataResponse {
		try {
			$result = $this->trackmaniaAPIService->getMapRecords($this->userId, [$accountId], [$mapId]);
		} catch (TokenRefreshException|TmApiRequestException $e) {
			return $this->getExceptionResponse($e);
		} catch (\Throwable $e) {
			return new DataResponse($e->getMessage(), Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}
}
