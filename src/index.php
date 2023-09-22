<?php

require_once __DIR__ . '/lib.php';

set_error_handler('error_handler', E_ALL);

// $_SERVER['REQUEST_URI'] = '/api/v1/auth/login';
// $_SERVER['REQUEST_METHOD'] = 'GET';

get('/', fn() => 'Hello world');