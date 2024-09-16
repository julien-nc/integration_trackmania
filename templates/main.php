<?php

use OCA\Trackmania\AppInfo\Application;
use OCP\Util;

$appId = Application::APP_ID;
Util::addScript($appId, $appId . '-main');
Util::addStyle($appId, $appId . '-main');
