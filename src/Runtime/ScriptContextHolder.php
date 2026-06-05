<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Closure;

final class ScriptContextHolder
{
    private const int ROOT_COROUTINE = 0;

    /** @var array<int, list<ScriptContext>> */
    private static array $contexts = [];

    public static function set(ScriptContext $context): void
    {
        self::$contexts[self::coroutineId()][] = $context;
    }

    public static function clear(): void
    {
        $id = self::coroutineId();

        if (!isset(self::$contexts[$id])) {
            return;
        }

        array_pop(self::$contexts[$id]);

        if (self::$contexts[$id] === []) {
            unset(self::$contexts[$id]);
        }
    }

    public static function current(): ScriptContext
    {
        $stack = self::$contexts[self::coroutineId()] ?? [];
        $context = $stack === [] ? null : $stack[array_key_last($stack)];

        if ($context === null) {
            throw new \LogicException('bia() called outside of a script context. This function is only available inside scripts executed by Bia.');
        }

        return $context;
    }

    public static function run(ScriptContext $context, Closure $body): mixed
    {
        self::set($context);

        try {
            return $body();
        } finally {
            self::clear();
        }
    }

    private static function coroutineId(): int
    {
        if (!class_exists(\Swoole\Coroutine::class)) {
            return self::ROOT_COROUTINE;
        }

        $id = \Swoole\Coroutine::getCid();

        return $id > 0 ? $id : self::ROOT_COROUTINE;
    }
}
