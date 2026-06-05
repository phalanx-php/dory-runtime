<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaExecutionContext;
use Phalanx\Bia\Runtime\ScriptContextHolder;
use Phalanx\Bia\Runtime\ScriptRunner;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScriptRunnerTest extends TestCase
{
    #[Test]
    public function executes_script_and_returns_result(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $bia = new BiaExecutionContext($scope, __DIR__ . '/../../Fixtures/return-42.php', $config);

        $result = ScriptRunner::execute($bia);

        self::assertSame(42, $result);
    }

    #[Test]
    public function script_receives_bia_context(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $bia = new BiaExecutionContext($scope, __DIR__ . '/../../Fixtures/echo-bia.php', $config);

        ob_start();
        $result = ScriptRunner::execute($bia);
        $output = ob_get_clean();

        self::assertSame(0, $result);
        self::assertSame("leonidas\n", $output);
    }

    #[Test]
    public function script_exception_propagates(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $bia = new BiaExecutionContext($scope, __DIR__ . '/../../Fixtures/throw-error.php', $config);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('phalanx broken');

        ScriptRunner::execute($bia);
    }

    #[Test]
    public function bia_outside_context_throws_logic_exception(): void
    {
        ScriptContextHolder::clear();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('bia() called outside of a script context');

        bia();
    }

    #[Test]
    public function context_is_cleared_after_execution(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $bia = new BiaExecutionContext($scope, __DIR__ . '/../../Fixtures/return-42.php', $config);

        ScriptRunner::execute($bia);

        $this->expectException(\LogicException::class);
        bia();
    }

    #[Test]
    public function context_is_cleared_after_exception(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $bia = new BiaExecutionContext($scope, __DIR__ . '/../../Fixtures/throw-error.php', $config);

        try {
            ScriptRunner::execute($bia);
        } catch (\RuntimeException) {
        }

        $this->expectException(\LogicException::class);
        bia();
    }
}
