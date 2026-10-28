<?php

error_reporting(E_ALL);

ini_set('memory_limit', '512M');
ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

$loader = require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/ContractFixture.php';
require_once __DIR__ . '/ApiRouteTestCase.php';
require_once __DIR__ . '/TranslationCoverageTestCase.php';

if (file_exists(($copilotAutoload = __DIR__ . '/../playground/site/plugins/kirby-copilot/vendor/autoload.php'))) {
    require_once $copilotAutoload;
    // Composer prepends each autoloader, so a Kirby in Copilot's dev tree would shadow this one.
    $loader->unregister();
    $loader->register(true);
}
