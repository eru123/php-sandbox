<?php

function sandbox(string $key, string $code, array $args = [], array $cfg = [])
{
    $shared_dir = acfg($cfg, 'shared', []);
    $shared_dir = is_array($shared_dir) ? $shared_dir : [$shared_dir];
    $shared_dir = array_map('realpath', $shared_dir);
    $shared_dir = array_filter($shared_dir, fn ($dir) => $dir && is_dir($dir));

    $scripts_dir = acfg($cfg, 'scripts', false);
    $scripts_dir = empty($scripts_dir) ? false : realpath($scripts_dir);
    if (!$scripts_dir) {
        throw new Exception("Scripts directory not found", 500);
    }

    $dir = $scripts_dir . "/$key";
    $target = "$dir/index.php";

    if (file_exists($target) && !is_writable($target)) {
        throw new Exception("File $target is not writable", 500);
    }

    if (!automkdir($dir)) {
        throw new Exception("Failed to create sandbox environment", 500);
    }

    $f = fopen($target, 'w');
    fwrite($f, $code);
    fclose($f);

    $dir = realpath($dir);
    $target = realpath($target);
    $php = acfg($cfg, 'php', trim(shell_exec('where php')));
    $ini = realpath($scripts_dir . '/php.ini');

    foreach ($shared_dir as $shared) {
        $shared = realpath($shared);
        if ($shared && is_dir($shared)) {
            recursive_copy($shared, $dir);
        }
    }

    $cargs = ['cd', $dir, '&&', $php, '-c', $ini];
    $args += ['open_basedir' => $dir];
    foreach ($args as $k => $v) {
        $cargs[] = "-d";
        $cargs[] = "$k=$v";
    }
    unset($args);
    $cargs[] = $target;
    $cmd = cmdp($cargs);
    ob_start();
    echo shell_exec($cmd);
    $contents = ob_get_contents();
    ob_end_clean();
    return $contents;
}

function automkdir(string $dir): bool
{
    if (is_dir($dir)) {
        return true;
    }
    return mkdir($dir, 0755, true);
}

function recursive_copy($src, $dst)
{
    $dir = opendir($src);
    automkdir($dst);
    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') continue;
        $from = $src . DIRECTORY_SEPARATOR . $file;
        $to = $dst . DIRECTORY_SEPARATOR . $file;
        if (is_dir($from)) {
            recursive_copy($from, $to);
        } else if (file_exists($to)) {
            unlink($to);
            copy($from, $to);
        } else {
            copy($from, $to);
        }
    }
    closedir($dir);
}

function noshell_exec(string $cmd): string|false
{
    static $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $options = ['bypass_shell' => true];

    if (!$proc = proc_open($cmd, $descriptors, $pipes, null, null, $options)) {
        throw new Error('Creating child process failed');
    }

    fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($proc);
    return $result;
}

function parallel_exec(string $cmd): void
{
    if (substr(php_uname(), 0, 7) == "Windows") {
        pclose(popen("start /B " . $cmd, "r"));
    } else {
        exec($cmd . " > /dev/null &");
    }
}

function escape_win32_argv(string $value): string
{
    static $expr = '(
        [\x00-\x20\x7F"] # control chars, whitespace or double quote
      | \\\\++ (?=("|$)) # backslashes followed by a quote or at the end
    )ux';

    if ($value === '') {
        return '""';
    }
    $quote = false;
    $replacer = function ($match) use ($value, &$quote) {
        switch ($match[0][0]) { // only inspect the first byte of the match
            case '"': // double quotes are escaped and must be quoted
                $match[0] = '\\"';
            case ' ':
            case "\t": // spaces and tabs are ok but must be quoted
                $quote = true;
                return $match[0];
            case '\\': // matching backslashes are escaped if quoted
                return $match[0] . $match[0];
            default:
                throw new InvalidArgumentException(sprintf(
                    "Invalid byte at offset %d: 0x%02X",
                    strpos($value, $match[0]),
                    ord($match[0])
                ));
        }
    };

    $escaped = preg_replace_callback($expr, $replacer, (string)$value);
    if ($escaped === null) {
        throw preg_last_error() === PREG_BAD_UTF8_ERROR
            ? new InvalidArgumentException("Invalid UTF-8 string")
            : new Error("PCRE error: " . preg_last_error());
    }

    return $quote // only quote when needed
        ? '"' . $escaped . '"'
        : $value;
}

function escape_win32_cmd(string $value): string
{
    return preg_replace('([()%!^"<>&|])', '^$0', $value);
}

function cmdp(string|array $cmd): string
{
    if (is_array($cmd) && count($cmd) && isset($cmd[0])) {
        $f = __SCRIPTS__ . DIRECTORY_SEPARATOR . $cmd[0] . '.php';
        if (file_exists($f)) {
            array_shift($cmd);
            array_unshift($cmd, 'php', $f);
        }
    }
    $cmd = is_array($cmd) ? implode(' ', array_map(PHP_OS_FAMILY === 'Windows' ? 'escape_win32_argv' : 'trim', $cmd)) : $cmd;
    return $cmd;
}

function cmd(string|array $cmd, $parallel = false): string|false|null
{
    $cmd = cmdp($cmd);
    $cmd = PHP_OS_FAMILY === 'Windows' ? escape_win32_cmd($cmd) : $cmd;
    return $parallel ? parallel_exec($cmd) : noshell_exec($cmd);
}

function xshell(string|array $cmd): string|false|null
{
    return shell_exec(cmdp($cmd));
}
