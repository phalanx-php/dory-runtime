<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\CommandGroup;

class BiaCommandGroup
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

        return CommandGroup::of($commands, 'bia');
    }
}
