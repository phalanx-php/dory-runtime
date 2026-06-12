<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

final class BiaConfigIssue
{
    public function __construct(
        private(set) string $code,
        private(set) string $message,
        private(set) string $path,
    ) {
    }
}
