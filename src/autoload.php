<?php

session_start();
$_PARAMS = [];

define('__SCRIPTS__', __DIR__ . '/../scripts');
define('__SHARED__', __DIR__ . '/../shared');
define('DEBUG', true);

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/sys.php';
require_once __DIR__ . '/cfg.php';
