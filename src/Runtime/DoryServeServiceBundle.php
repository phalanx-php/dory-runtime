<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Phalanx\Boot\AppContext;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;

final class DoryServeServiceBundle extends ServiceBundle
{
    public function __construct(private DoryServeConfig $config)
    {
    }

    public function services(Services $services, AppContext $context): void
    {
        $config = $this->config;

        $services->singleton(DoryServeConfig::class)
            ->factory(static fn(): DoryServeConfig => $config);
    }
}
