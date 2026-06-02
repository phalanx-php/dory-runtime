<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

final class ReferenceQuery
{
    public function __construct(
        public ?string $kind = null,
        public ?string $name = null,
        public ?string $receiver = null,
        public ?string $file = null,
        public ?string $context = null,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind,
            'name' => $this->name,
            'receiver' => $this->receiver,
            'file' => $this->file,
            'context' => $this->context,
        ], static fn (?string $value): bool => $value !== null);
    }
}
