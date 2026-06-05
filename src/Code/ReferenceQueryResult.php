<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class ReferenceQueryResult
{
    /**
     * @param list<ReferenceRecord> $references
     * @param list<ParseError> $errors
     */
    public function __construct(
        public string $root,
        public array $references,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'root'),
            array_map(ReferenceRecord::fromArray(...), PayloadReader::listOfObjects($data, 'references')),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
        );
    }
}
