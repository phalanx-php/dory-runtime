<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

use RuntimeException;

final class InvalidCodePayload extends RuntimeException
{
    public static function field(string $field, string $expected): self
    {
        return new self("Bia code parser returned invalid `{$field}`; expected {$expected}.");
    }

    public static function json(): self
    {
        return new self('Bia code parser returned invalid JSON.');
    }
}
