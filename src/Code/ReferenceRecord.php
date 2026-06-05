<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class ReferenceRecord
{
    public function __construct(
        public string $kind,
        public string $name,
        public ?string $receiver,
        public SpanRecord $span,
        public ?string $context,
        public ?string $file = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'kind'),
            PayloadReader::string($data, 'name'),
            PayloadReader::nullableString($data, 'receiver'),
            SpanRecord::fromArray(PayloadReader::object($data, 'span')),
            PayloadReader::nullableString($data, 'context'),
            PayloadReader::nullableString($data, 'file'),
        );
    }
}
