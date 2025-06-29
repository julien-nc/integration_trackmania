<?php

/**
 * Nextcloud - Trackmania integration
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2023
 */

namespace OCA\Trackmania\Controller;

use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Service\SecretService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\IConfig;

use OCP\IRequest;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppConfig $appConfig,
		private SecretService $secretService,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return TemplateResponse
	 * @throws \Exception
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$coreToken = $this->secretService->getEncryptedUserValue($this->userId, $prefix . 'token');
		$refreshToken = $this->secretService->getEncryptedUserValue($this->userId, $prefix . 'refresh_token');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$ubisoftUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$accountId = $this->config->getUserValue($this->userId, Application::APP_ID, $prefix . 'account_id');
		$userFlagCode = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_flag_code');
		$userZoneName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_zone_name');
		$hasClientID = $this->appConfig->getValueString(Application::APP_ID, 'client_id') !== '';
		$hasClientSecret = $this->appConfig->getValueString(Application::APP_ID, 'client_secret') !== '';

		$pageInitialState = [
			'core_token' => ($coreToken && $refreshToken) ? 'dummyTokenContent' : '',
			'account_id' => $accountId,
			'user_name' => $userName,
			'user_flag_code' => $userFlagCode,
			'user_zone_name' => $userZoneName,
			'ubisofts_user_id' => $ubisoftUserId,
			'has_oauth_credentials' => $hasClientID && $hasClientSecret,
		];
		$this->initialStateService->provideInitialState('user-config', $pageInitialState);

		// table config
		$tableConfig = [
			'other_account_id' => '',
			'other_account_name' => '',
			'other_account_flag_code' => '',
			'other_account_zone_name' => '',
		];
		foreach ($this->config->getUserKeys($this->userId, Application::APP_ID) as $key) {
			if (str_starts_with($key, 'show_column_')) {
				$tableConfig[$key] = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			}
			if (str_starts_with($key, 'filter_')) {
				$tableConfig[$key] = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			}
			if (str_starts_with($key, 'sort_')) {
				$tableConfig[$key] = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			}
			if (str_starts_with($key, 'other_account_')) {
				$tableConfig[$key] = $this->config->getUserValue($this->userId, Application::APP_ID, $key);
			}
		}
		$this->initialStateService->provideInitialState('table-config', $tableConfig);

		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
