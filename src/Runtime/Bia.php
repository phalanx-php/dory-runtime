<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Boot\AppContext;

class Bia
{
    /** @param array<string, mixed> $context */
    public static function starting(array $context = []): BiaBuilder
    {
        return new BiaBuilder(new AppContext($context));
    }
}
