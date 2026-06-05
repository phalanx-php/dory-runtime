<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

final readonly class BiaServeConfig
{
    public function __construct(
        public string $scriptPath,
        public BiaConfig $biaConfig,
    ) {
    }
}
