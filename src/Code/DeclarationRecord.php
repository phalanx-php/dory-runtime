<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

final class DeclarationRecord
{
    public function __construct(
        public string $kind,
        public string $name,
        public ?string $namespace,
        public ?string $declaringType,
        public string $fqn,
        public SpanRecord $span,
        public SpanRecord $nameSpan,
        public ?string $file = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'kind'),
            PayloadReader::string($data, 'name'),
            PayloadReader::nullableString($data, 'namespace'),
            PayloadReader::nullableString($data, 'declaring_type'),
            PayloadReader::string($data, 'fqn'),
            SpanRecord::fromArray(PayloadReader::object($data, 'span')),
            SpanRecord::fromArray(PayloadReader::object($data, 'name_span')),
            PayloadReader::nullableString($data, 'file'),
        );
    }
}
