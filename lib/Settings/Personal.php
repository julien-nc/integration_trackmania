<?php
namespace OCA\Trackmania\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Trackmania\AppInfo\Application;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$liveToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'live_token');
		$mmUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$mmUserName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');

		$userConfig = [
			'token' => $liveToken ? 'dummyTokenContent' : '',
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
