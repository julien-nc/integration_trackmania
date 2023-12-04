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

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'config#isUserConnected', 'url' => '/is-connected', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],

		['name' => 'trackmaniaAPI#getMyFavorites', 'url' => '/favorites', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMyRecords', 'url' => '/pbs', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapThumbnail', 'url' => '/thumbnail/{id}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTop', 'url' => '/map/{mapUid}/top', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapFinishCount', 'url' => '/map/{mapUid}/finish-count', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTimePosition', 'url' => '/map/{mapUid}/position/{time}', 'verb' => 'GET'],
		// TODO implement what would be my position if i gain 1ms 10ms 100ms 1000ms
		// TODO implement show my top position in %
		// TODO check how it's possible to get the real number of finishes
		// because https://live-services.trackmania.nadeo.live/api/token/leaderboard/group/{groupUid}/map/{mapUid}/top
		// only gives the first 10000 records
		// FOUND IT: https://map-monitor.xk.io/map/{mapUid}/nb_players/refresh
		// we can use that and fallback to the capped one
	],
];
