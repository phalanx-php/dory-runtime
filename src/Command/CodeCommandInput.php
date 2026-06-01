<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandContext;
use Phalanx\Dory\Code\ParseError;

final class CodeCommandInput
{
    public static function nullableOption(CommandContext $ctx, string $name): ?string
    {
        $value = $ctx->options->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function hasAnyOption(CommandContext $ctx, string ...$names): bool
    {
        foreach ($names as $name) {
            if (self::nullableOption($ctx, $name) !== null) {
                return true;
            }
        }

        return false;
    }

    public static function errorLine(ParseError $error): string
    {
        return "  {$error->message}";
    }
}
