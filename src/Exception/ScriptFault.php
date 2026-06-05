<?php

declare(strict_types=1);

namespace Phalanx\Bia\Exception;

use RuntimeException;
use Throwable;

final class ScriptFault extends RuntimeException
{
    private function __construct(
        Throwable $cause,
        private(set) string $scriptLabel,
        private(set) string $sourceFile,
        private(set) int $lineOffset,
        private(set) bool $isInline,
    ) {
        parent::__construct($cause->getMessage(), $cause->getCode(), $cause);
    }

    public static function inline(Throwable $cause): self
    {
        return new self(
            cause: $cause,
            scriptLabel: '<inline>',
            sourceFile: $cause->getFile(),
            lineOffset: 1,
            isInline: true,
        );
    }

    public static function file(Throwable $cause, string $path): self
    {
        return new self(
            cause: $cause,
            scriptLabel: basename($path),
            sourceFile: $path,
            lineOffset: 0,
            isInline: false,
        );
    }

    public function scriptLine(): int
    {
        return $this->getPrevious()->getLine() - $this->lineOffset;
    }
}
