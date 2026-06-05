<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class ParseResult
{
    /**
     * @param list<ParseError> $errors
     * @param list<TokenRecord> $tokens
     * @param list<DeclarationRecord> $declarations
     * @param list<CodeNodeRecord> $nodes
     * @param list<ReferenceRecord> $references
     */
    public function __construct(
        public SourceFileRecord $file,
        public bool $hasErrors,
        public array $errors,
        public array $tokens,
        public array $declarations,
        public array $nodes,
        public array $references,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            SourceFileRecord::fromArray(PayloadReader::object($data, 'file')),
            PayloadReader::bool($data, 'has_errors'),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
            array_map(TokenRecord::fromArray(...), PayloadReader::listOfObjects($data, 'tokens')),
            array_map(DeclarationRecord::fromArray(...), PayloadReader::listOfObjects($data, 'declarations')),
            array_map(CodeNodeRecord::fromArray(...), PayloadReader::listOfObjects($data, 'nodes')),
            array_map(ReferenceRecord::fromArray(...), PayloadReader::listOfObjects($data, 'references')),
        );
    }
}
