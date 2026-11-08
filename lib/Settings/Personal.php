<?php

namespace OCA\Trackmania\Settings;

use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Service\SecretService;
use OCA\Trackmania\Service\TrackmaniaAPIService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;
use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IUserConfig $userConfig,
		private IAppConfig $appConfig,
		private SecretService $secretService,
		private TrackmaniaAPIService $trackmaniaAPIService,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 * @throws \Exception
	 */
	public function getForm(): TemplateResponse {
		$coreToken = $this->secretService->getEncryptedUserValue($this->userId, Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'] . 'token');
		// the zone info and display name may be incomplete when the user connected with a service account
		$this->trackmaniaAPIService->refreshStoredZoneInfo($this->userId);
		$mmUserId = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'user_id');
		$mmUserName = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'user_name');
		$mmUserDisplayName = $this->userConfig->getValueString($this->userId, Application::APP_ID, 'user_display_name');

		$hasClientID = $this->appConfig->getValueString(Application::APP_ID, 'client_id') !== '';
		$hasClientSecret = $this->appConfig->getValueString(Application::APP_ID, 'client_secret') !== '';

		$userConfig = [
			'core_token' => $coreToken ? 'dummyTokenContent' : '',
			'user_id' => $mmUserId,
			'user_name' => $mmUserName,
			'user_display_name' => $mmUserDisplayName,
			'has_oauth_credentials' => $hasClientID && $hasClientSecret,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
