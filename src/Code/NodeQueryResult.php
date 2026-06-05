<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class NodeQueryResult
{
    /**
     * @param list<CodeNodeRecord> $nodes
     * @param list<ParseError> $errors
     */
    public function __construct(
        public string $root,
        public array $nodes,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'root'),
            array_map(CodeNodeRecord::fromArray(...), PayloadReader::listOfObjects($data, 'nodes')),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
        );
    }
}
