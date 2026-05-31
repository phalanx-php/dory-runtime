<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

final readonly class DoryServeConfig
{
    public function __construct(
        public string $scriptPath,
        public DoryConfig $doryConfig,
    ) {
    }
}
