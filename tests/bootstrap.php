<?php

declare(strict_types=1);

use JardisSupport\DotEnv\DotEnv;

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/vendor/autoload.php';

try {
    (new DotEnv())->loadPublic(__DIR__ . '/../');
} catch (Exception $e) {

}
