<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Command;

use Phalanx\Archon\Command\CommandArgs;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Dory\Command\RunCommand;
use Phalanx\Dory\Runtime\DoryScriptExecutor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunCommandTest extends TestCase
{
    #[Test]
    public function throws_when_script_does_not_exist(): void
    {
        $scope = $this->buildScope('/nonexistent/thermopylae/script.php');
        $command = new RunCommand(new DoryScriptExecutor());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Script not found');

        $command($scope);
    }

    #[Test]
    public function throws_for_relative_nonexistent_path(): void
    {
        $scope = $this->buildScope('no-such-directory/olympus.php');
        $command = new RunCommand(new DoryScriptExecutor());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Script not found');

        $command($scope);
    }

    private function buildScope(string $scriptPath): CommandContext
    {
        $args = new CommandArgs(['script' => $scriptPath]);

        $scope = $this->createStub(CommandContext::class);
        $scope->method('$args::get')->willReturn($args);

        return $scope;
    }
}
