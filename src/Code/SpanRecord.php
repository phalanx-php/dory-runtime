<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class SpanRecord
{
    public function __construct(
        public int $startOffset,
        public int $endOffset,
        public int $startLine,
        public int $startColumn,
        public int $endLine,
        public int $endColumn,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::int($data, 'start_offset'),
            PayloadReader::int($data, 'end_offset'),
            PayloadReader::int($data, 'start_line'),
            PayloadReader::int($data, 'start_column'),
            PayloadReader::int($data, 'end_line'),
            PayloadReader::int($data, 'end_column'),
        );
    }
}
