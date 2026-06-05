<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Console\Output\StreamOutput;
use Phalanx\Task\Scopeable;

class InitCommand implements Scopeable, DescribesCommand
{
    private const string SAMPLE_SCRIPT = <<<'PHP'
        <?php

        declare(strict_types=1);

        bia()->println('Greetings from Olympus.');

        $result = bia()->attempt(static fn(): string => 'The phalanx holds.')
            ->timeout(5.0)
            ->run();

        bia()->dump($result);

        return 0;
        PHP;

    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);

        $directory = (string) $ctx->args->get('directory', '.');
        $absolute = realpath($directory) ?: $directory;

        if (!is_dir($absolute) && !mkdir($absolute, 0755, recursive: true)) {
            fwrite(STDERR, "Could not create directory: {$directory}\n");
            return 1;
        }

        $scriptName = (string) ($ctx->options->get('name') ?? 'hello.php');
        $scriptPath = rtrim($absolute, '/') . '/' . $scriptName;

        if (file_exists($scriptPath) && !$ctx->options->flag('force')) {
            $output->persist("File already exists: {$scriptPath}");
            $output->persist('Use --force to overwrite.');
            return 0;
        }

        file_put_contents($scriptPath, self::SAMPLE_SCRIPT . "\n");

        $output->persist("Created: {$scriptPath}");
        $output->persist('');
        $output->persist('Run it with:');
        $output->persist("  bia run {$scriptPath}");

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Scaffold a new Bia project with a sample script',
            arguments: [
                Arg::optional('directory', 'Target directory', '.'),
            ],
            options: [
                Opt::value('name', 'n', 'Script filename', 'hello.php'),
                Opt::flag('force', 'f', 'Overwrite existing files'),
            ],
            examples: [
                'bia init my-project',
                'bia new . --name=sync.php',
                'bia init scripts -f',
            ],
            aliases: ['new'],
        );
    }
}
