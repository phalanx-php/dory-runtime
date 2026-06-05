<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class ParseError
{
    public function __construct(
        public string $message,
        public SpanRecord $span,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'message'),
            SpanRecord::fromArray(PayloadReader::object($data, 'span')),
        );
    }
}
