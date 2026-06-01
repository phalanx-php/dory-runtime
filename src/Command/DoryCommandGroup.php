<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandGroup;

class DoryCommandGroup
{
    public static function commands(): CommandGroup
    {
        $commands = [
            'run' => RunCommand::class,
            'r' => RunCommand::class,
            'init' => InitCommand::class,
            'new' => InitCommand::class,
            'doctor' => DoctorCommand::class,
            'check' => DoctorCommand::class,
            'code' => CodeCommandGroup::commands(),
            'serve' => ServeCommand::class,
        ];

        if (class_exists(\Phalanx\DoryBin\Command\BuildCommandGroup::class)) {
            $commands['build'] = \Phalanx\DoryBin\Command\BuildCommandGroup::commands();
        }

        return CommandGroup::of($commands, 'dory');
    }
}
