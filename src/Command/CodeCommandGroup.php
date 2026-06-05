<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\CommandGroup;

class CodeCommandGroup
{
    public static function commands(): CommandGroup
    {
        return CommandGroup::of([
            'check' => CodeCheckCommand::class,
            'declarations' => CodeDeclarationsCommand::class,
            'nodes' => CodeNodesCommand::class,
            'references' => CodeReferencesCommand::class,
            'tokens' => CodeTokensCommand::class,
        ], 'bia code');
    }
}
