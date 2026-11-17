<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/vendor/autoload.php';

$_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? 'base64:TestAppKeyForCampusFindUnitTesting32B=';
$_ENV['APP_ENV'] = 'testing';
