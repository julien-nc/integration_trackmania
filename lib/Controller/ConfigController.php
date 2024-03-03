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

use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;

use OCP\IRequest;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function isUserConnected(): DataResponse {
		$token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		return new DataResponse([
			'connected' => $token !== '',
		]);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse {
		if (isset($values['login'], $values['password'])) {
			return $this->loginWithCredentials($values['login'], $values['password']);
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['core_token']) && $values['core_token'] === '') {
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'account_id');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
			foreach (Application::AUDIENCES as $audienceKey => $v) {
				$prefix = $v['token_config_key_prefix'];
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'token');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'refresh_token');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'token_expires_at');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'account_id');
			}
			$result['user_id'] = '';
			$result['user_name'] = '';
			$result['user_displayname'] = '';
		}
		return new DataResponse($result);
	}

	/**
	 * @param string $url
	 * @param string $login
	 * @param string $password
	 * @return DataResponse
	 * @throws \OCP\PreConditionNotMetException
	 */
	private function loginWithCredentials(string $login, string $password): DataResponse {
		// cleanup refresh token and expiration date on classic login
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');

		$result = $this->trackmaniaAPIService->login($login, $password);
		if (isset($result['userId'], $result['nameOnPlatform'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $result['userId'] ?? '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $result['nameOnPlatform'] ?? '');

			foreach (Application::AUDIENCES as $audienceKey => $v) {
				$prefix = $v['token_config_key_prefix'];
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'token', $result[$audienceKey]['accessToken']);
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'refresh_token', $result[$audienceKey]['refreshToken']);
				$decodedToken = $this->decodeToken($result[$audienceKey]['accessToken']);
				$expiresAt = $decodedToken['exp'];
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'token_expires_at', $expiresAt);
				$accountId = $decodedToken['sub'];
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'account_id', $accountId);
			}

			return new DataResponse([
				'user_id' => $result['userId'] ?? '',
				'user_name' => $result['nameOnPlatform'] ?? '',
			]);
		}
		return new DataResponse([
			'user_id' => '',
			'user_name' => '',
			'user_displayname' => '',
		]);
	}

	public static function decodeToken(string $token): array {
		$parts = explode('.', $token);
		if (count($parts) === 3) {
			$payload = $parts[1];
			$decodedPayload = base64_decode($payload);
			$tokenArray = json_decode($decodedPayload, true);
			return $tokenArray;
		}
		throw new \Exception('Impossible to decode token');
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}
}
