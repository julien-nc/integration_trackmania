<?php

use OCP\Util;

$appId = OCA\Trackmania\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-adminSettings');
?>

<div id="trackmania_prefs"></div>
