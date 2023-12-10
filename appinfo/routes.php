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
		['name' => 'trackmaniaAPI#toggleFavorite', 'url' => '/map/favorite/{mapUid}/{maction}', 'verb' => 'POST'],
		['name' => 'trackmaniaAPI#getMyRecords', 'url' => '/pbs', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapThumbnail', 'url' => '/thumbnail/{id}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTop', 'url' => '/map/{mapUid}/top', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapFinishCount', 'url' => '/map/{mapUid}/finish-count', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTimePosition', 'url' => '/map/{mapUid}/position/{time}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTimeImprovements', 'url' => '/map/{mapUid}/improvements/{time}', 'verb' => 'GET'],
		// TODO implement what would be my position if i gain 1ms 10ms 100ms 1000ms
		// TODO graph number of tracks / position in leaderboard
	],
];
