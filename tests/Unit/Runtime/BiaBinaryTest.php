<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaBinaryTest extends TestCase
{
    #[Test]
    public function no_args_show_top_level_help(): void
    {
        $result = self::runBia();

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertStringContainsString('Usage:', $result->stdout);
        self::assertStringContainsString('contract', $result->stdout);
        self::assertStringContainsString('run', $result->stdout);
    }

    #[Test]
    public function contract_prints_the_active_bootstrap_contract(): void
    {
        $result = self::runBia('contract');

        self::assertSame(0, $result->exitCode, $result->stderr);

        $payload = json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('2.0', $payload['contract']);
        self::assertSame('Phalanx\\Phalanx', $payload['entrypoint']);
        self::assertSame('phalanx-php/phalanx', $payload['package']);
        self::assertSame('2.0-dev', $payload['version']);
    }

    #[Test]
    public function run_executes_script_files(): void
    {
        $result = self::runBia('run', dirname(__DIR__, 2) . '/Fixtures/return-42.php');

        self::assertSame(42, $result->exitCode, $result->stderr);
    }

    private static function runBia(string ...$args): CommandResult
    {
        $command = [PHP_BINARY, dirname(__DIR__, 3) . '/bin/bia', ...$args];
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3),
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new CommandResult(proc_close($process), $stdout, $stderr);
    }
}

final class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }
}
