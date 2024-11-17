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
		['name' => 'config#setSensitiveAdminConfig', 'url' => '/sensitive-admin-config', 'verb' => 'PUT'],

		['name' => 'trackmaniaAPI#getMyFavorites', 'url' => '/favorites', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#toggleFavorite', 'url' => '/map/favorite/{mapUid}/{maction}', 'verb' => 'POST'],
		['name' => 'trackmaniaAPI#getMyRecords', 'url' => '/pbs', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getAccountInfo', 'url' => '/account/{accountId}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#searchAccount', 'url' => '/account/search/{name}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getFlag', 'url' => '/flag/{code}', 'verb' => 'GET'],
		// partial load
		['name' => 'trackmaniaAPI#getMyRawRecords', 'url' => '/pbs/raw', 'verb' => 'POST'],
		['name' => 'trackmaniaAPI#getMoreRecordInfo', 'url' => '/pbs/info', 'verb' => 'POST'],
		// end partial load
		['name' => 'trackmaniaAPI#getMapThumbnail', 'url' => '/map/{mapId}/thumbnail', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getLiveMapInfo', 'url' => '/map/{mapUid}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getCoreMapInfoById', 'url' => '/core/map/id/{mapId}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getCoreMapInfoByUid', 'url' => '/core/map/uid/{mapUid}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTop', 'url' => '/map/{mapUid}/top', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapFinishCount', 'url' => '/map/{mapUid}/finish-count', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTimePosition', 'url' => '/map/{mapUid}/position/{time}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getMapTimeImprovements', 'url' => '/map/{mapUid}/improvements/{time}', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#test', 'url' => '/test', 'verb' => 'GET'],
		['name' => 'trackmaniaAPI#getAccountRecordOnMap', 'url' => '/map/{mapId}/record/{accountId}', 'verb' => 'GET'],
		// TODO implement what would be my position if i gain 1ms 10ms 100ms 1000ms
		// TODO graph number of tracks / position in leaderboard
	],
];
