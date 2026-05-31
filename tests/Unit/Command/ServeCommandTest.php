<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Command;

use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\CommandOptions;
use Phalanx\Boot\AppContext;
use Phalanx\Dory\Command\ServeCommand;
use Phalanx\Stoa\StoaServerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ServeCommandTest extends TestCase
{
    #[Test]
    public function empty_env_preserves_stoa_server_defaults(): void
    {
        $config = self::serverConfig($this->scope(env: []));

        self::assertSame('0.0.0.0', $config->host);
        self::assertSame(8080, $config->port);
    }

    #[Test]
    public function app_env_values_feed_stoa_server_config(): void
    {
        $config = self::serverConfig($this->scope(env: [
            'APP_HOST' => '127.0.0.1',
            'APP_PORT' => '9001',
        ]));

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(9001, $config->port);
    }

    #[Test]
    public function command_options_override_env_values(): void
    {
        $config = self::serverConfig($this->scope(
            env: [
                'APP_HOST' => '127.0.0.1',
                'APP_PORT' => '9001',
            ],
            options: [
                'host' => '0.0.0.0',
                'port' => '9100',
            ],
        ));

        self::assertSame('0.0.0.0', $config->host);
        self::assertSame(9100, $config->port);
    }

    #[Test]
    public function command_config_exposes_explicit_stoa_mode(): void
    {
        $options = ServeCommand::commandConfig()->options;
        $names = array_map(static fn($option): string => $option->name, $options);

        self::assertContains('stoa', $names);
    }

    /**
     * @param array<string, mixed> $env
     * @param array<string, mixed> $options
     */
    private function scope(array $env, array $options = []): CommandContext
    {
        $scope = $this->createStub(CommandContext::class);
        $scope->method('$options::get')->willReturn(new CommandOptions($options));
        $scope->method('service')->willReturnCallback(
            static fn(string $type) => match ($type) {
                AppContext::class => new AppContext(['env' => $env]),
                default => throw new \RuntimeException('Unexpected service: ' . $type),
            },
        );

        return $scope;
    }

    private static function serverConfig(CommandContext $scope): StoaServerConfig
    {
        $method = new ReflectionMethod(ServeCommand::class, 'serverConfig');

        return $method->invoke(null, $scope);
    }
}
