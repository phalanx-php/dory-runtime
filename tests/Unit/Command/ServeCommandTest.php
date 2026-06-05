<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Command;

use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\CommandOptions;
use Phalanx\Boot\AppContext;
use Phalanx\Bia\Command\ServeCommand;
use Phalanx\Bia\Runtime\BiaProjectConfig;
use Phalanx\Http\HttpServerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class ServeCommandTest extends TestCase
{
    #[Test]
    public function empty_env_preserves_http_server_defaults(): void
    {
        $config = self::serverConfig($this->scope(env: []));

        self::assertSame('0.0.0.0', $config->host);
        self::assertSame(8080, $config->port);
    }

    #[Test]
    public function env_values_feed_http_server_config(): void
    {
        $config = self::serverConfig($this->scope(env: [
            'host' => '127.0.0.1',
            'port' => '9001',
        ]));

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(9001, $config->port);
    }

    #[Test]
    public function phalanx_env_values_feed_http_server_config(): void
    {
        $config = self::serverConfig($this->scope(env: [
            'PHALANX_HOST' => '10.0.0.1',
            'PHALANX_PORT' => '3000',
        ]));

        self::assertSame('10.0.0.1', $config->host);
        self::assertSame(3000, $config->port);
    }

    #[Test]
    public function command_options_override_env_values(): void
    {
        $config = self::serverConfig($this->scope(
            env: [
                'PHALANX_HOST' => '127.0.0.1',
                'PHALANX_PORT' => '9001',
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
    public function listen_option_sets_host_and_port(): void
    {
        $config = self::serverConfig($this->scope(
            env: [],
            options: ['listen' => '127.0.0.1:9000'],
        ));

        self::assertSame('127.0.0.1', $config->host);
        self::assertSame(9000, $config->port);
    }

    #[Test]
    public function explicit_host_port_override_listen(): void
    {
        $config = self::serverConfig($this->scope(
            env: [],
            options: [
                'listen' => '127.0.0.1:9000',
                'host' => '0.0.0.0',
                'port' => '9100',
            ],
        ));

        self::assertSame('0.0.0.0', $config->host);
        self::assertSame(9100, $config->port);
    }

    #[Test]
    public function invalid_listen_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        self::serverConfig($this->scope(
            env: [],
            options: ['listen' => 'no-colon'],
        ));
    }

    #[Test]
    public function command_config_exposes_explicit_http_mode(): void
    {
        $options = ServeCommand::commandConfig()->options;
        $names = array_map(static fn($option): string => $option->name, $options);

        self::assertContains('http', $names);
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
                AppContext::class => new AppContext($env),
                BiaProjectConfig::class => new BiaProjectConfig([], null),
                default => throw new \RuntimeException('Unexpected service: ' . $type),
            },
        );

        return $scope;
    }

    private static function serverConfig(CommandContext $scope): HttpServerConfig
    {
        $method = new ReflectionMethod(ServeCommand::class, 'serverConfig');

        return $method->invoke(null, $scope);
    }
}
