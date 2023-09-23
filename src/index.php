<?php

require_once __DIR__ . '/autoload.php';

$file = realpath(__DIR__ . '/../scripts/test.php');
$f = fopen($file, 'r');
$contents = fread($f, filesize($file));

post('/', function () {
    $data = json_data();
    if (!isset($data['code']) || empty($data['code'])) {
        throw new Exception('Empty code', 400);
    }

    $headers = getallheaders();
    $sandbox_id = acfg($headers, 'X-Ctrl-Session-Id', session_id());

    return sandbox($sandbox_id, (string) $data['code'], [
        'max_execution_time' => int_minmax((int) acfg($headers, 'X-Ctrl-Max-Execution-Time', 1), 1, 30),
        'memory_limit' => int_minmax((int) acfg($headers, 'X-Ctrl-Memory-Limit'), 2, 128, 4) . 'M',
    ], [
        'scripts' => __SCRIPTS__,
        'shared' => __SHARED__,
        'php' => 'php',
    ]);
});


any('/.*', function () {
    return response_handler([
        'error' => [
            'code' => 404,
            'message' => 'Not found'
        ]
    ], 404);
});