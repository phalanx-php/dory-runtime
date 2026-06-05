<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class DeclarationQuery
{
    public function __construct(
        public ?string $kind = null,
        public ?string $name = null,
        public ?string $fqn = null,
        public ?string $file = null,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'name' => $this->name,
            'fqn' => $this->fqn,
            'file' => $this->file,
        ], static fn (?string $value): bool => $value !== null);
    }
}
