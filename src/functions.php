<?php

declare(strict_types=1);

use Phalanx\Cancellation\Halted;
use Phalanx\Bia\Runtime\ScriptContext;
use Phalanx\Bia\Runtime\ScriptContextHolder;

if (!function_exists('bia')) {
    function bia(): ScriptContext
    {
        return ScriptContextHolder::current();
    }
}

if (!function_exists('dump')) {
    function dump(mixed ...$values): void
    {
        foreach ($values as $value) {
            bia()->dump($value);
        }
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        foreach ($values as $value) {
            bia()->dump($value);
        }

        throw new Halted('dd');
    }
}
