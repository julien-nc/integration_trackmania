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
		['name' => 'trackmaniaAPI#getUserAvatar', 'url' => '/users/{userId}/image', 'verb' => 'GET'],
	],
];
