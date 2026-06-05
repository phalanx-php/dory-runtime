<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class TokenQuery
{
    public function __construct(
        public ?string $kind = null,
        public ?string $text = null,
        public ?string $file = null,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'text' => $this->text,
            'file' => $this->file,
        ], static fn (?string $value): bool => $value !== null);
    }
}
