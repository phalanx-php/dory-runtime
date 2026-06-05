<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;

final class BiaServeServiceBundle extends ServiceBundle
{
    public function __construct(private BiaServeConfig $config)
    {
    }

    public function services(Services $services, AppContext $context): void
    {
        $config = $this->config;

        $services->singleton(BiaServeConfig::class)
            ->factory(static fn(): BiaServeConfig => $config);
    }
}
