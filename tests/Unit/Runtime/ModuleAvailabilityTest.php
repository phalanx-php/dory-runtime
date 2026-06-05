<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Config\ValidationContext;
use Phalanx\DevServer\DevServer;
use Phalanx\Filesystem\FilesystemServiceBundle;
use Phalanx\HttpClient\HttpServiceBundle;
use Phalanx\Network\NetworkConfig;
use Phalanx\Network\NetworkServiceBundle;
use Phalanx\WebSocket\Client\WsClientConfig;
use Phalanx\WebSocket\WsServiceBundle;
use Phalanx\Worker\WorkerServiceBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleAvailabilityTest extends TestCase
{
    #[Test]
    public function auto_registered_module_bundles_exist(): void
    {
        self::assertTrue(class_exists(NetworkServiceBundle::class));
        self::assertTrue(class_exists(WsServiceBundle::class));
        self::assertTrue(class_exists(HttpServiceBundle::class));
        self::assertTrue(class_exists(FilesystemServiceBundle::class));
    }

    #[Test]
    public function opt_in_module_bundle_exists(): void
    {
        self::assertTrue(class_exists(WorkerServiceBundle::class));
    }

    #[Test]
    public function dev_server_is_class_available(): void
    {
        self::assertTrue(class_exists(DevServer::class));
    }

    #[Test]
    public function network_config_passes_validation_with_defaults(): void
    {
        $config = new NetworkConfig();

        self::assertTrue($config->configured);
        self::assertEmpty($config->validate(new ValidationContext()));
    }

    #[Test]
    public function websocket_client_config_constructs_with_defaults(): void
    {
        $config = WsClientConfig::default();

        self::assertIsFloat($config->connectTimeout);
        self::assertGreaterThan(0, $config->connectTimeout);
    }
}
