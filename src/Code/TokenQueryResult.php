<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

final class TokenQueryResult
{
    /**
     * @param list<TokenRecord> $tokens
     * @param list<ParseError> $errors
     */
    public function __construct(
        public string $root,
        public array $tokens,
        public array $errors,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            PayloadReader::string($data, 'root'),
            array_map(TokenRecord::fromArray(...), PayloadReader::listOfObjects($data, 'tokens')),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
        );
    }
}
