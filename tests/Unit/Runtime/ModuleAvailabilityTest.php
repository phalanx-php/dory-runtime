<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Runtime;

use Phalanx\Argos\NetworkConfig;
use Phalanx\Argos\NetworkServiceBundle;
use Phalanx\Grammata\FilesystemServiceBundle;
use Phalanx\Hermes\Client\WsClientConfig;
use Phalanx\Hermes\WsServiceBundle;
use Phalanx\Hydra\HydraServiceBundle;
use Phalanx\Hydra\ParallelConfig;
use Phalanx\Iris\HttpServiceBundle;
use Phalanx\Skopos\Skopos;
use Phalanx\Themis\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleAvailabilityTest extends TestCase
{
    #[Test]
    public function auto_registered_modules_are_available(): void
    {
        self::assertTrue(class_exists(NetworkServiceBundle::class));
        self::assertTrue(class_exists(WsServiceBundle::class));
        self::assertTrue(class_exists(HttpServiceBundle::class));
        self::assertTrue(class_exists(FilesystemServiceBundle::class));
    }

    #[Test]
    public function opt_in_modules_are_available(): void
    {
        self::assertTrue(class_exists(HydraServiceBundle::class));
    }

    #[Test]
    public function skopos_is_class_available(): void
    {
        self::assertTrue(class_exists(Skopos::class));
    }

    #[Test]
    public function argos_config_has_safe_defaults(): void
    {
        $config = new NetworkConfig();

        self::assertSame(5.0, $config->defaultTimeout);
        self::assertSame(50, $config->defaultConcurrency);
        self::assertEmpty($config->validate(new ValidationContext()));
    }

    #[Test]
    public function hermes_client_config_has_safe_defaults(): void
    {
        $config = WsClientConfig::default();

        self::assertSame(5.0, $config->connectTimeout);
        self::assertSame(65536, $config->maxMessageSize);
    }

    #[Test]
    public function hydra_config_has_safe_defaults(): void
    {
        $config = ParallelConfig::default();

        self::assertSame(4, $config->agents);
        self::assertSame(100, $config->mailboxLimit);
    }
}
