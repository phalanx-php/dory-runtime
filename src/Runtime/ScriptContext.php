<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Closure;
use Phalanx\Bia\Orchestration\AttemptBuilder;
use Phalanx\Bia\Scoped\ScopedCode;
use Phalanx\Bia\Scoped\ScopedFiles;
use Phalanx\Bia\Scoped\ScopedHttpClient;
use Phalanx\Scope\ExecutionScope;

interface ScriptContext extends ExecutionScope
{
    public string $scriptPath { get; }

    public string $scriptName { get; }

    public BiaConfig $config { get; }

    public ScopedHttpClient $http { get; }

    public ScopedFiles $fs { get; }

    public ScopedCode $code { get; }

    public function attempt(Closure $task): AttemptBuilder;

    public function dump(mixed ...$values): void;

    public function println(string $message = ''): void;
}
