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

use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

use OCA\Trackmania\AppInfo\Application;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @return TemplateResponse
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$prefix = Application::AUDIENCES[Application::AUDIENCE_CORE]['token_config_key_prefix'];
		$coreToken = $this->config->getUserValue($this->userId, Application::APP_ID, $prefix . 'token');
		$refreshToken = $this->config->getUserValue($this->userId, Application::APP_ID, $prefix . 'refresh_token');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$ubisoftUserId = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_id');
		$accountId = $this->config->getUserValue($this->userId, Application::APP_ID, $prefix . 'account_id');

		$pageInitialState = [
			'core_token' => ($coreToken && $refreshToken) ? 'dummyTokenContent' : '',
			'account_id' => $accountId,
			'user_name' => $userName,
			'ubisofts_user_id' => $ubisoftUserId,
		];
		$this->initialStateService->provideInitialState('user-config', $pageInitialState);

		// table config
		$tableConfig = [];
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
		}
		$this->initialStateService->provideInitialState('table-config', $tableConfig);

		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
