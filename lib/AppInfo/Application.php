<?php
/**
 * Nextcloud - Trackmania
 *
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Trackmania\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_trackmania';

	public const CACHE_DURATION = 24 * 60 * 60;
	public const INTEGRATION_USER_AGENT = 'Nextcloud Trackmania integration / eneiluj+ubi@posteo.net';

	public const TOKEN_REFRESH_URL = 'https://prod.trackmania.core.nadeo.online/v2/authentication/token/refresh';
	public const AUDIENCE_CORE = 'NadeoServices';
	public const AUDIENCE_LIVE = 'NadeoLiveServices';
	public const AUDIENCE_CLUB = 'NadeoClubServices';
	public const AUDIENCES = [
		self::AUDIENCE_CORE => [
			'base_url' => 'https://prod.trackmania.core.nadeo.online/',
			'token_config_key_prefix' => 'core_',
		],
		self::AUDIENCE_LIVE => [
			'base_url' => 'https://live-services.trackmania.nadeo.live/api/token/',
			'token_config_key_prefix' => 'live_',
		],
		self::AUDIENCE_CLUB => [
			'base_url' => 'https://meet.trackmania.nadeo.club/api/',
			'token_config_key_prefix' => 'club_',
		],
	];
	public const TRACKMANIA_IO_API_URL = 'https://trackmania.io/api/';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
	}

	public function boot(IBootContext $context): void {
	}
}
