<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Closure;
use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaScriptExecutor;
use Phalanx\Mark\Mark;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaScriptExecutorTest extends TestCase
{
    #[Test]
    public function wrapsScriptExecutionInMarkTimeout(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'bia_executor_');
        self::assertIsString($script);
        file_put_contents($script, "<?php\nreturn 7;\n");

        $childScope = $this->createStub(ExecutionScope::class);
        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('timeout')
            ->with(
                self::callback(static fn(Mark $duration): bool => $duration->toSeconds() === 12.5),
                self::isInstanceOf(Closure::class),
            )
            ->willReturnCallback(static fn(Mark $duration, Closure $task): mixed => $task($childScope));

        try {
            $result = (new BiaScriptExecutor())->execute($scope, $script, new BiaConfig(scriptTimeout: 12.5));
        } finally {
            @unlink($script);
        }

        self::assertSame(7, $result);
    }
}
