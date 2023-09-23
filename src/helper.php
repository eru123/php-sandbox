<?php

function acfg(array $cfg, string $key, mixed $default = null): mixed
{
    return isset($cfg[$key]) ? $cfg[$key] : $default;
}

function int_minmax(int $value, int $min, int $max, int $default = null): int
{
    return $value < $min || $value > $max ? (!is_int($default) ? $value : $default) : $value;
}