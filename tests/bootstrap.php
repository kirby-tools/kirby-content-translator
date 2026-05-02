<?php

error_reporting(E_ALL);

ini_set('memory_limit', '512M');
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../index.php';

if (file_exists(($copilotAutoload = __DIR__ . '/../playground/site/plugins/kirby-copilot/vendor/autoload.php'))) {
    require_once $copilotAutoload;
}
