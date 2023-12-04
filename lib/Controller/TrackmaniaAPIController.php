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
		$result = $this->trackmaniaAPIService->getAllMapsWithPosition($this->userId);
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
	public function getMapThumbnail(string $id, string $fallbackName = '?'): Response {
		$url = Application::AUDIENCES[Application::AUDIENCE_CORE]['base_url'] . 'storageObjects/' . $id;
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
}
