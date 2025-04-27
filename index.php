<?php

require_once __DIR__ . '/vendor/autoload.php';

use PicoPHP\Core;

$congiguration = [
    'rootDirectory' => __DIR__,
    'routesDirectory' => __DIR__ . '/app',
    'viewDirectory' => __DIR__ . '/views'
];

$pico = new Core($congiguration);
$pico->run();
