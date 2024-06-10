<?php

use Kirby\Cms\App;

App::plugin('johannschopplich/playground', [
    'commands' => require __DIR__ . '/extensions/commands.php'
]);
