<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class CodeNodeRecord
{
    public function __construct(
        public string $kind,
        public ?string $name,
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
            PayloadReader::nullableString($data, 'name'),
            SpanRecord::fromArray(PayloadReader::object($data, 'span')),
            PayloadReader::nullableString($data, 'context'),
            PayloadReader::nullableString($data, 'file'),
        );
    }
}
