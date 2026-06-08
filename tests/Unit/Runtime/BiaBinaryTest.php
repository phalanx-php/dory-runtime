<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class BiaBinaryTest extends TestCase
{
    #[Test]
    public function no_args_show_top_level_help(): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/bin/bia']);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('Usage:', $process->getOutput());
        self::assertStringContainsString('run', $process->getOutput());
        self::assertStringContainsString('doctor', $process->getOutput());
        self::assertStringNotContainsString('Missing required argument: script', $process->getErrorOutput());
    }

    #[Test]
    public function run_inline_still_executes_through_default_command(): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/bin/bia', 'run', '1 + 1']);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('2', $process->getOutput());
    }

    #[Test]
    public function dd_dumps_and_exits_successfully(): void
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 3) . '/bin/bia', 'run', 'dd("halt")']);
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertStringContainsString('"halt"', $process->getOutput());
        self::assertStringNotContainsString('ERROR', $process->getOutput());
    }
}
