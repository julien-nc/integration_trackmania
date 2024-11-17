<?php

namespace OCA\Trackmania\Settings;

use OCA\Trackmania\AppInfo\Application;
use OCA\Trackmania\Service\SecretService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private SecretService $secretService,
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
		$mmUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$mmUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');

		$userConfig = [
			'core_token' => $coreToken ? 'dummyTokenContent' : '',
			'user_id' => $mmUserId,
			'user_name' => $mmUserName,
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
