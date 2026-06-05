<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class CodeProjectIndex
{
    /**
     * @param list<SourceFileRecord> $files
     * @param list<ParseError> $errors
     */
    public function __construct(
        public string $root,
        public array $files,
        public int $fileCount,
        public int $declarationCount,
        public int $tokenCount,
        public int $nodeCount,
        public int $referenceCount,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'root'),
            array_map(SourceFileRecord::fromArray(...), PayloadReader::listOfObjects($data, 'files')),
            PayloadReader::int($data, 'file_count'),
            PayloadReader::int($data, 'declaration_count'),
            PayloadReader::int($data, 'token_count'),
            PayloadReader::int($data, 'node_count'),
            PayloadReader::int($data, 'reference_count'),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
        );
    }
}
