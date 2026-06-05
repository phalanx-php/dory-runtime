<?php

declare(strict_types=1);

$autoload = null;
foreach ([
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 3) . '/phalanx/vendor/autoload.php',
    dirname(__DIR__, 3) . '/vendor/autoload.php',
] as $candidate) {
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}

if ($autoload === null) {
    throw new RuntimeException('Cannot find autoload.php');
}

$loader = require $autoload;

$loader->addPsr4('Phalanx\\Bia\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('Phalanx\\Bia\\Tests\\', dirname(__DIR__) . '/tests/');

$functionsFile = dirname(__DIR__) . '/src/functions.php';

if (is_file($functionsFile) && !function_exists('bia')) {
    require $functionsFile;
}
