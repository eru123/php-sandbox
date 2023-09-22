<?php

declare(strict_types=1);

function get(string $uri, ...$callbacks)
{
    return request('GET', $uri, ...$callbacks);
}

function post(string $uri, ...$callbacks)
{
    return request('POST', $uri, ...$callbacks);
}

function request(string $method, string $uri, ...$callbacks)
{
    if (strtoupper($_SERVER['REQUEST_METHOD']) === strtoupper($method) && uri_match($uri)) {
        $res = callback_handler($callbacks);
        return response_handler($res);
    }
}

function uri_match(string $uri): bool
{
    global $_PARAMS;
    $uri = preg_replace('/\//', '\\\/', $uri);
    $rgx = '/\{([a-zA-Z_]([a-zA-Z0-9_]+)?)\}|\$([a-zA-Z_]([a-zA-Z0-9_]+)?)|\:([a-zA-Z_]([a-zA-Z0-9_]+)?)/';
    $rgx = preg_replace_callback($rgx, fn ($m) => "(?P<" . ($m[1] ?: $m[3] ?: $m[5]) . ">[^\/\?]+)", $uri);
    $rgx = '/^' . $rgx . '$/';
    $match = !!preg_match($rgx, (string) $_SERVER['REQUEST_URI'], $params);
    $_PARAMS = $match ? array_filter($params, fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY) : $_PARAMS;
    return $match;
}

function callback_handler($callbacks, $res = null)
{
    while ($callback = array_shift($callbacks)) {
        $callback = make_callable($callback);
        try {
            $res = call_user_func_array($callback, is_array($res) ? array_values($res) : (!empty($res) ? [$res] : []));
        } catch (Exception $e) {
            return error_handler($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
        }
        if ($res === false) return;
    };
    return $res;
}

function response_handler($res, $code = 200)
{
    if (
        $res === false ||
        empty($res) 
    ) return;
    
    $hs = headers_sent();

    $hs || http_response_code($code);
    if (is_array($res) xor is_object($res)) {
        $hs || header('Content-Type: application/json');
        echo json_encode($res);
    } else {
        echo $res;
    }
    exit;
}

function make_callable($cb)
{
    if (is_callable($cb)) {
        return $cb;
    }

    if (is_string($cb)) {
        $rgx = '/^([a-zA-Z0-9_\\\\]+)(::|@)([a-zA-Z0-9_]+)$/';
        if (preg_match($rgx, $cb, $matches)) {
            $classname = $matches[1];
            $method = $matches[3];
            if (class_exists($classname)) {
                $obj = new $classname();
                if (method_exists($obj, $method)) {
                    return [$obj, $method];
                }
            }
        }
    }

    if (is_array($cb) && count($cb) == 2) {
        if (is_object($cb[0]) && is_string($cb[1])) {
            return $cb;
        } else if (is_string($cb[0]) && is_string($cb[1])) {
            $classname = $cb[0];
            $method = $cb[1];
            if (class_exists($classname)) {
                $obj = new $classname();
                if (method_exists($obj, $method)) {
                    return [$obj, $method];
                } else if (method_exists($classname, $method)) {
                    return $cb;
                }
            }
        }
    }

    throw new Exception('invalid callback');
}

function error_handler($errno, $errstr, $errfile, $errline)
{
    $http_code = preg_match('/^[1-5][0-9][0-9]$/', (string) $errno) ? (int) $errno : 500;
    $errstr = $errstr ?: 'Unknown error';
    $errfile = $errfile ?: 'Unknown file';
    $errline = $errline ?: 'Unknown line';

    return response_handler([
        'error' => [
            'code' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]
    ], $http_code);
}
