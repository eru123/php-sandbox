<?php

require_once __DIR__ . '/lib.php';

define('__SCRIPTS__', __DIR__ . '/../scripts');
set_error_handler('error_handler', E_ALL);

// $_SERVER['REQUEST_URI'] = '/api/v1/auth/login';
// $_SERVER['REQUEST_METHOD'] = 'GET';

get('/', fn() => create_isolated_file('test', "<?php\n\necho \"hello world\";"));

function create_isolated_file($key, $code)
{
    $tmp = tempnam(sys_get_temp_dir(), 'isolated');
    file_put_contents($tmp, $code);
    // get php executable path
    $php = trim(shell_exec('which php'));
    // get php.ini path
    $ini = trim(shell_exec('php -i | grep "Loaded Configuration File" | cut -d" " -f 5'));
    // get php.ini dir
    $dir = trim(shell_exec('php -i | grep "Scan this dir for additional .ini files" | cut -d" " -f 10'));
    // get php.ini files
    // $ini = array_merge([$ini], glob($dir . '/*.ini'));
    // get php.ini directives
    // $ini = array_map(fn ($file) => trim(shell_exec("cat $file | grep -E '^[a-zA-Z_]+[a-zA-Z0-9_]*' | cut -d' ' -f 1")), $ini);
    // get php.ini directives
    // $ini = array_filter($ini, fn ($ini) => !empty($ini));
    // get php.ini directives
    // $ini = array_unique($ini);
    // get php.ini directives
    // $ini = array_map(fn ($ini) => "-d $ini", $ini);
    // get php.ini directives
    // $ini = implode(' ', $ini);
    echo '<pre>';
    var_dump($ini);
    var_dump($php);
    var_dump($tmp);
    var_dump($dir);
    echo '</pre>';
}