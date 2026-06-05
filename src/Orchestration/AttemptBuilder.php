<?php

declare(strict_types=1);

namespace Phalanx\Bia\Orchestration;

use Closure;
use Phalanx\Cancellation\Cancelled;
use Phalanx\Mark\Mark;
use Phalanx\Recovery\RecoveryPlan;
use Phalanx\Scope\ExecutionScope;
use Throwable;

class AttemptBuilder
{
    private ?RecoveryPlan $recoveryPlan = null;
    private ?Mark $timeoutDuration = null;
    private ?string $singleflightKey = null;
    private ?Closure $catchHandler = null;
    private ?Closure $finallyCallback = null;

    public function __construct(
        private ExecutionScope $scope,
        private Closure $task,
    ) {
    }

    public function retry(int $times, ?RecoveryPlan $plan = null): self
    {
        $this->recoveryPlan = $plan ?? RecoveryPlan::defaultRetry(attempts: $times);
        return $this;
    }

    public function timeout(float $seconds): self
    {
        $this->timeoutDuration = Mark::s($seconds);
        return $this;
    }

    public function singleflight(string $key): self
    {
        $this->singleflightKey = $key;
        return $this;
    }

    public function catch(Closure $handler): self
    {
        $this->catchHandler = $handler;
        return $this;
    }

    public function finally(Closure $callback): self
    {
        $this->finallyCallback = $callback;
        return $this;
    }

    public function run(): mixed
    {
        $task = $this->task;
        $scope = $this->scope;

        $execute = static function () use ($scope, $task): mixed {
            return $scope->execute($task);
        };

        if ($this->recoveryPlan !== null) {
            $plan = $this->recoveryPlan;
            $prev = $execute;
            $execute = static function () use ($scope, $prev, $plan): mixed {
                return $scope->retry($prev, $plan);
            };
        }

        if ($this->timeoutDuration !== null) {
            $duration = $this->timeoutDuration;
            $prev = $execute;
            $execute = static function () use ($scope, $prev, $duration): mixed {
                return $scope->timeout($duration, $prev);
            };
        }

        if ($this->singleflightKey !== null) {
            $key = $this->singleflightKey;
            $prev = $execute;
            $execute = static function () use ($scope, $prev, $key): mixed {
                return $scope->singleflight($key, $prev);
            };
        }

        $catchHandler = $this->catchHandler;
        $finallyCallback = $this->finallyCallback;

        if ($catchHandler !== null || $finallyCallback !== null) {
            try {
                return $execute();
            } catch (Throwable $e) {
                if ($e instanceof Cancelled) {
                    throw $e;
                }
                if ($catchHandler !== null) {
                    return $catchHandler($e);
                }
                throw $e;
            } finally {
                if ($finallyCallback !== null) {
                    $finallyCallback();
                }
            }
        }

        return $execute();
    }
}
