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
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCP\IURLGenerator;

class TrackmaniaAPIController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private IURLGenerator $urlGenerator,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function test(): DataResponse {
		return new DataResponse($this->trackmaniaAPIService->test($this->userId));
	}

	/**
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMyFavorites(): DataResponse {
		$result = $this->trackmaniaAPIService->getFavoritesWithPosition($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
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
		} catch (ClientException $e) {
			$response = $e->getResponse();
			$statusCode = $response->getStatusCode();
			if ($statusCode === Http::STATUS_UNAUTHORIZED) {
				$body = $response->getBody();
				$parsedResponse = json_decode($body, true);
				$result = [
					'error' => 'trackmania_request_failed',
					'status_code' => $statusCode,
					'response' => $parsedResponse,
				];
				return new DataResponse($result, Http::STATUS_BAD_REQUEST);
			}
		}
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @return DataResponse
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapThumbnail(string $thumbnailId, string $fallbackName = '?'): Response {
		$thumbnailId = preg_replace('/\//', '', $thumbnailId);
		$url = Application::AUDIENCES[Application::AUDIENCE_CORE]['base_url'] . 'storageObjects/' . $thumbnailId;
		$image = $this->trackmaniaAPIService->getImage($url);
		if ($image !== null && isset($image['body'], $image['headers'])) {
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
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getLiveMapInfo(string $mapUid): DataResponse {
		$result = $this->trackmaniaAPIService->getLiveMapInfo($this->userId, [$mapUid]);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @param string $mapId
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCoreMapInfoById(string $mapId): DataResponse {
		$result = $this->trackmaniaAPIService->getCoreMapInfo($this->userId, [$mapId]);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getCoreMapInfoByUid(string $mapUid): DataResponse {
		$result = $this->trackmaniaAPIService->getCoreMapInfo($this->userId, null, [$mapUid]);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
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
		$result = $this->trackmaniaAPIService->getMapTop($this->userId, $mapUid, $offset, $length, $onlyWorld);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @param string $mapUid
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapFinishCount(string $mapUid): DataResponse {
		return new DataResponse($this->trackmaniaAPIService->getMapFinishCount($this->userId, $mapUid));
	}

	/**
	 * @param string $mapUid
	 * @param int $time
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTimePosition(string $mapUid, int $time): DataResponse {
		return new DataResponse($this->trackmaniaAPIService->getScorePosition($this->userId, $mapUid, $time));
	}

	/**
	 * @param string $mapUid
	 * @param int $time
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getMapTimeImprovements(string $mapUid, int $time): DataResponse {
		return new DataResponse($this->trackmaniaAPIService->getScoreImprovements($this->userId, $mapUid, $time));
	}

	/**
	 * @param string $mapUid
	 * @param string $maction
	 * @return DataResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function toggleFavorite(string $mapUid, string $maction): DataResponse {
		$result = $this->trackmaniaAPIService->toggleFavorite($this->userId, $mapUid, $maction);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
