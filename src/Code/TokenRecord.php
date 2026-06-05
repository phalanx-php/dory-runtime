<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class TokenRecord
{
    public function __construct(
        public string $kind,
        public string $text,
        public SpanRecord $span,
        public ?string $file = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'kind'),
            PayloadReader::string($data, 'text'),
            SpanRecord::fromArray(PayloadReader::object($data, 'span')),
            PayloadReader::nullableString($data, 'file'),
        );
    }
}
