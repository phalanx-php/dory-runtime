<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandGroup;

class CodeCommandGroup
{
    public static function commands(): CommandGroup
    {
        return CommandGroup::of([
            'check' => CodeCheckCommand::class,
            'declarations' => CodeDeclarationsCommand::class,
            'tokens' => CodeTokensCommand::class,
        ], 'dory code');
    }
}
