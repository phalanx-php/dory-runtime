<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Command;

use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Console\Output\TerminalEnvironment;
use Phalanx\Bia\Command\DoctorCommand;
use Phalanx\Bia\Runtime\BiaConfig;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoctorCommandTest extends TestCase
{
    #[Test]
    #[RequiresPhpExtension('swoole')]
    public function returns_zero_when_environment_is_healthy(): void
    {
        [$scope] = $this->buildScope();
        $command = new DoctorCommand();
        $result = $command($scope);

        self::assertSame(0, $result);
    }

    #[Test]
    public function output_contains_pass_for_php_version(): void
    {
        [$scope, $stream] = $this->buildScope();
        $command = new DoctorCommand();
        $command($scope);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertStringContainsString('[pass]', $output);
        self::assertStringContainsString('PHP >= 8.4', $output);
    }

    #[Test]
    public function output_contains_swoole_check(): void
    {
        [$scope, $stream] = $this->buildScope();
        $command = new DoctorCommand();
        $command($scope);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertStringContainsString('Swoole loaded', $output);
    }

    #[Test]
    public function output_contains_config_validation(): void
    {
        [$scope, $stream] = $this->buildScope();
        $command = new DoctorCommand();
        $command($scope);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertStringContainsString('Bia config', $output);
    }

    #[Test]
    public function reports_invalid_config(): void
    {
        $config = new BiaConfig(scriptTimeout: 0.0);
        [$scope, $stream] = $this->buildScope($config);
        $command = new DoctorCommand();
        $command($scope);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertStringContainsString('[fail]', $output);
        self::assertStringContainsString('Bia config', $output);
    }

    /**
     * @return array{CommandContext, resource}
     */
    private function buildScope(?BiaConfig $config = null): array
    {
        $config ??= new BiaConfig();
        $stream = fopen('php://memory', 'rw');
        self::assertIsResource($stream);

        $terminal = new TerminalEnvironment(isTty: false);
        $output = new StreamOutput($stream, $terminal);

        $scope = $this->createStub(CommandContext::class);
        $scope->method('service')->willReturnCallback(
            static fn(string $type) => match ($type) {
                StreamOutput::class => $output,
                BiaConfig::class => $config,
                default => throw new \RuntimeException('Unexpected service: ' . $type),
            },
        );

        return [$scope, $stream];
    }
}
