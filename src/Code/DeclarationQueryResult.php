<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class DeclarationQueryResult
{
    /**
     * @param list<DeclarationRecord> $declarations
     * @param list<ParseError> $errors
     */
    public function __construct(
        public string $root,
        public array $declarations,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'root'),
            array_map(DeclarationRecord::fromArray(...), PayloadReader::listOfObjects($data, 'declarations')),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
        );
    }
}
