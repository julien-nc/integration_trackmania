<?php

use OCP\Util;

$appId = OCA\Trackmania\AppInfo\Application::APP_ID;
Util::addScript($appId, $appId . '-personalSettings');
?>

<div id="trackmania_prefs"></div>
