<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

final class SourceFileRecord
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $wrapped,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'id'),
            PayloadReader::string($data, 'name'),
            PayloadReader::bool($data, 'wrapped'),
        );
    }
}
