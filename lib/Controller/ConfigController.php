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
use OCA\Trackmania\Service\SecretService;
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Exceptions\AppConfigTypeConflictException;
use OCP\IAppConfig;
use OCP\IConfig;

use OCP\IRequest;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private SecretService $secretService,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return DataResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	public function isUserConnected(): DataResponse {
		return new DataResponse([
			'connected' => $this->userId !== null && $this->trackmaniaAPIService->isUserConnected($this->userId),
		]);
	}

	/**
	 * set config values
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	#[NoAdminRequired]
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
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_flag_code');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_zone_name');
			foreach (Application::AUDIENCES as $audienceKey => $v) {
				$prefix = $v['token_config_key_prefix'];
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'token');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'refresh_token');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'token_expires_at');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, $prefix . 'account_id');
			}
			$result['user_id'] = '';
			$result['user_name'] = '';
		}
		return new DataResponse($result);
	}

	/**
	 * Set sensitive admin config values
	 *
	 * @param array $values key/value pairs to store in app config
	 * @return DataResponse
	 * @throws AppConfigTypeConflictException
	 */
	#[PasswordConfirmationRequired]
	public function setSensitiveAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			if (in_array($key, ['client_id', 'client_secret'], true)) {
				$this->appConfig->setValueString(Application::APP_ID, $key, $value, false, true);
			} else {
				$this->appConfig->setValueString(Application::APP_ID, $key, $value);
			}
		}
		return new DataResponse([]);
	}

	/**
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
				$this->secretService->setEncryptedUserValue($this->userId, $prefix . 'token', $result[$audienceKey]['accessToken']);
				$this->secretService->setEncryptedUserValue($this->userId, $prefix . 'refresh_token', $result[$audienceKey]['refreshToken']);
				$decodedToken = $this->decodeToken($result[$audienceKey]['accessToken']);
				$expiresAt = $decodedToken['exp'];
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'token_expires_at', $expiresAt);
				$accountId = $decodedToken['sub'];
				$this->config->setUserValue($this->userId, Application::APP_ID, $prefix . 'account_id', $accountId);

				if ($audienceKey === Application::AUDIENCE_CORE) {
					$zoneInfo = $this->getAccountZoneInfo($decodedToken['sub']);
				}
			}

			$corePrefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
			return new DataResponse([
				'user_id' => $this->config->getUserValue($this->userId, Application::APP_ID, $corePrefix . 'account_id'),
				'user_name' => $result['nameOnPlatform'],
				'user_flag_code' => $zoneInfo['user_flag_code'] ?? '',
				'user_zone_name' => $zoneInfo['user_zone_name'] ?? '',
			]);
		}
		return new DataResponse([
			'user_id' => '',
			'user_name' => '',
		]);
	}

	private function getAccountZoneInfo(string $accountId): array {
		$searchResult = $this->trackmaniaAPIService->searchAccount($accountId);
		if (isset($searchResult['error']) || count($searchResult) !== 1) {
			return [];
		}
		$account = $searchResult[0];
		if (!isset($account['player'])) {
			return [];
		}
		$player = $account['player'];
		if (!isset($player['zone'])) {
			return [];
		}
		$zone = $player['zone'];
		$zoneNames = [$zone['name']];
		$flags = [$zone['flag']];
		while (isset($zone['parent'])) {
			$zone = $zone['parent'];
			$zoneNames[] = $zone['name'];
			$flags[] = $zone['flag'];
		}

		// flag
		$flag = 'WOR';
		if (count($flags) > 2) {
			$flag = $flags[count($flags) - 3];
		} elseif (count($flags) > 1) {
			$flag = $flags[count($flags) - 2];
		}
		$this->config->setUserValue($this->userId, Application::APP_ID, 'user_flag_code', $flag);
		$this->config->setUserValue($this->userId, Application::APP_ID, 'user_zone_name', implode(', ', $zoneNames));
		return [
			'user_flag_code' => $flag,
			'user_zone_name' => implode(', ', $zoneNames),
		];
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
