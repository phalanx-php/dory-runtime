<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Closure;
use Phalanx\Cancellation\Cancelled;
use Phalanx\Concurrency\SettlementBag;
use Phalanx\Dory\Orchestration\AttemptBuilder;
use Phalanx\Dory\Rendering\EchoSink;
use Phalanx\Dory\Rendering\OutputSink;
use Phalanx\Dory\Rendering\ValueRendererPipeline;
use Phalanx\Dory\Scoped\ScopedFiles;
use Phalanx\Dory\Scoped\ScopedHttpClient;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Support\ExecutionScopeDelegate;
use Phalanx\Task\Executable;
use Phalanx\Task\Scopeable;
use Throwable;

final class DoryExecutionContext implements ScriptContext
{
    use ExecutionScopeDelegate;

    public string $scriptName { get => basename($this->scriptPath); }

    public ScopedHttpClient $http {
        get => $this->_http ??= new ScopedHttpClient($this);
    }

    public ScopedFiles $fs {
        get => $this->_fs ??= new ScopedFiles($this);
    }

    private ?ScopedFiles $_fs = null;
    private ?ScopedHttpClient $_http = null;
    private ?OutputSink $_sink = null;
    private ?ValueRendererPipeline $_pipeline = null;

    private bool $pipelineResolved = false;

    public function __construct(
        private ExecutionScope $inner,
        private(set) string $scriptPath,
        private(set) DoryConfig $config,
    ) {
    }

    public function attempt(Closure $task): AttemptBuilder
    {
        return new AttemptBuilder($this, $this->wrapClosure($task));
    }

    /** @return array<string|int, mixed> */
    public function concurrent(Scopeable|Executable|Closure ...$tasks): array
    {
        return $this->innerScope()->concurrent(...$this->wrapTasks($tasks));
    }

    public function race(Scopeable|Executable|Closure ...$tasks): mixed
    {
        return $this->innerScope()->race(...$this->wrapTasks($tasks));
    }

    public function any(Scopeable|Executable|Closure ...$tasks): mixed
    {
        return $this->innerScope()->any(...$this->wrapTasks($tasks));
    }

    public function settle(Scopeable|Executable|Closure ...$tasks): SettlementBag
    {
        return $this->innerScope()->settle(...$this->wrapTasks($tasks));
    }

    /** @return array<string|int, mixed> */
    public function series(Scopeable|Executable|Closure ...$tasks): array
    {
        return $this->innerScope()->series(...$this->wrapTasks($tasks));
    }

    public function waterfall(Scopeable|Executable|Closure ...$tasks): mixed
    {
        return $this->innerScope()->waterfall(...$this->wrapTasks($tasks));
    }

    public function dump(mixed ...$values): void
    {
        $sink = $this->resolveSink();
        $pipeline = $this->resolvePipeline();

        foreach ($values as $value) {
            if ($pipeline !== null) {
                $pipeline->render($value, $sink);
            } else {
                $sink->line(is_string($value) ? $value : var_export($value, true));
            }
        }
    }

    public function println(string $message = ''): void
    {
        $this->resolveSink()->line($message);
    }

    protected function innerScope(): ExecutionScope
    {
        return $this->inner;
    }

    /**
     * @param array<Scopeable|Executable|Closure> $tasks
     * @return array<Scopeable|Executable|Closure>
     */
    private function wrapTasks(array $tasks): array
    {
        $scriptPath = $this->scriptPath;
        $config = $this->config;

        return array_map(
            static fn(Scopeable|Executable|Closure $task): Scopeable|Executable|Closure => $task instanceof Closure
                ? self::wrapClosureFor($task, $scriptPath, $config)
                : $task,
            $tasks,
        );
    }

    private function wrapClosure(Closure $task): Closure
    {
        return self::wrapClosureFor($task, $this->scriptPath, $this->config);
    }

    private static function wrapClosureFor(Closure $task, string $scriptPath, DoryConfig $config): Closure
    {
        return static function (ExecutionScope $scope) use ($task, $scriptPath, $config): mixed {
            $context = new self($scope, $scriptPath, $config);

            return ScriptContextHolder::run(
                $context,
                static fn(): mixed => $task($context),
            );
        };
    }

    private function resolveSink(): OutputSink
    {
        if ($this->_sink !== null) {
            return $this->_sink;
        }

        try {
            $resolved = $this->service(OutputSink::class);
            $sink = $resolved instanceof OutputSink ? $resolved : new EchoSink();
        } catch (Cancelled $e) {
            throw $e;
        } catch (Throwable) {
            $sink = new EchoSink();
        }

        return $this->_sink = $sink;
    }

    private function resolvePipeline(): ?ValueRendererPipeline
    {
        if ($this->pipelineResolved) {
            return $this->_pipeline;
        }

        try {
            $resolved = $this->service(ValueRendererPipeline::class);
            $this->_pipeline = $resolved instanceof ValueRendererPipeline ? $resolved : null;
        } catch (Cancelled $e) {
            throw $e;
        } catch (Throwable) {
            $this->_pipeline = null;
        }

        $this->pipelineResolved = true;

        return $this->_pipeline;
    }
}
