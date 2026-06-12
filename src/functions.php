<?php

declare(strict_types=1);

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        foreach ($values as $value) {
            fwrite(STDOUT, var_export($value, true) . PHP_EOL);
        }
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        dump(...$values);

        exit(0);
    }
}

if (!function_exists('bia_shutdown_requested')) {
    function bia_shutdown_requested(): int
    {
        return 0;
    }
}
