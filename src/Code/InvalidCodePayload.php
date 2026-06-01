<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

use RuntimeException;

final class InvalidCodePayload extends RuntimeException
{
    public static function field(string $field, string $expected): self
    {
        return new self("Dory code parser returned invalid `{$field}`; expected {$expected}.");
    }

    public static function json(): self
    {
        return new self('Dory code parser returned invalid JSON.');
    }
}
