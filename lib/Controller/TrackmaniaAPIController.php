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
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Http;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCP\IURLGenerator;

class TrackmaniaAPIController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getMyFavorites() {
		$result = $this->trackmaniaAPIService->getFavoritesWithPosition($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return DataResponse
	 * @throws Exception
	 */
	public function getMyRecords() {
		$result = $this->trackmaniaAPIService->getAllMapsWithPosition($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		} else {
			return new DataResponse($result);
		}
	}
}
