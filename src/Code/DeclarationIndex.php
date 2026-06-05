<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

final class DeclarationIndex
{
    /** @param list<DeclarationRecord> $items */
    public function __construct(
        public SourceFileRecord $file,
        public array $items,
    ) {
    }

    public static function fromParseResult(ParseResult $result): self
    {
        return new self($result->file, $result->declarations);
    }
}
