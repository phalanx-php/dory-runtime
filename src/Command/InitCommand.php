<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Boot\AppContext;
use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Filesystem\Files;
use Phalanx\Task\Scopeable;
use RuntimeException;

class InitCommand implements Scopeable, DescribesCommand
{
    private const string STARTER_SCRIPT = 'script';

    private const string STARTER_AGENT_HARNESS = 'agent-harness';

    private const string PHALANX_FRAMEWORK_VERSION = '2.0.x-dev';

    /** @var list<string> */
    private const array EXCLUDED_TEMPLATE_NAMES = [
        '.env',
        '.git',
        '.phpstan-cache',
        '.phpunit.result.cache',
        'composer.lock',
        'vendor',
    ];

    private const string SAMPLE_SCRIPT = <<<'PHP'
        <?php

        declare(strict_types=1);

        bia()->println('Hello from Bia.');

        $result = bia()->attempt(static fn(): string => 'The phalanx holds.')
            ->timeout(5.0)
            ->run();

        bia()->dump($result);

        return 0;
        PHP;

    public function __construct(
        private ?ProjectFiles $files = null,
    ) {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        $files = $this->files($ctx);

        $directory = (string) $ctx->args->get('directory', '.');
        $absolute = realpath($directory) ?: $directory;

        try {
            $files->ensureDirectory($absolute);
        } catch (RuntimeException) {
            $output->persist("Could not create directory: {$directory}");
            return 1;
        }

        $starter = (string) $ctx->options->get('starter', self::STARTER_SCRIPT);
        if ($starter === self::STARTER_AGENT_HARNESS) {
            return $this->createAgentHarnessStarter($ctx, $absolute, $output, $files);
        }

        if ($starter !== self::STARTER_SCRIPT) {
            $output->persist("Unknown starter: {$starter}");
            $output->persist('Available starters: script, agent-harness');
            return 1;
        }

        $scriptName = (string) ($ctx->options->get('name') ?? 'hello.php');
        $scriptPath = rtrim($absolute, '/') . '/' . $scriptName;

        if ($files->fileExists($scriptPath) && !$ctx->options->flag('force')) {
            $output->persist("File already exists: {$scriptPath}");
            $output->persist('Use --force to overwrite.');
            return 0;
        }

        $files->write($scriptPath, self::SAMPLE_SCRIPT . "\n");

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
                Opt::value('starter', '', 'Starter template: script or agent-harness', self::STARTER_SCRIPT),
                Opt::value('template-path', '', 'Local Agents starter template path'),
                Opt::value('framework-path', '', 'Local phalanx framework path'),
                Opt::flag('force', 'f', 'Overwrite existing files'),
            ],
            examples: [
                'bia init my-project',
                'bia new . --name=sync.php',
                'bia new my-agent-harness --starter=agent-harness',
                'bia init scripts -f',
            ],
            aliases: ['new'],
        );
    }

    private function createAgentHarnessStarter(
        CommandContext $ctx,
        string $target,
        StreamOutput $output,
        ProjectFiles $files,
    ): int {
        $template = $this->pathOption(
            $ctx,
            'template-path',
            'PHALANX_AGENT_HARNESS_PATH',
            dirname(__DIR__, 4) . '/starter-kits/agent-harness',
        );
        $framework = $this->pathOption(
            $ctx,
            'framework-path',
            'PHALANX_FRAMEWORK_PATH',
            dirname(__DIR__, 4) . '/phalanx',
        );

        if (!$files->directoryExists($template)) {
            $output->persist("Agents starter template not found: {$template}");
            return 1;
        }

        if (!$files->directoryExists($framework)) {
            $output->persist("Phalanx framework path not found: {$framework}");
            return 1;
        }

        $force = $ctx->options->flag('force');
        foreach ($files->templateFiles($template, self::EXCLUDED_TEMPLATE_NAMES) as $source => $relative) {
            $destination = rtrim($target, '/') . '/' . $relative;
            if ($files->fileExists($destination) && !$force) {
                $output->persist("File already exists: {$destination}");
                $output->persist('Use --force to overwrite.');
                return 0;
            }
        }

        foreach ($files->templateFiles($template, self::EXCLUDED_TEMPLATE_NAMES) as $source => $relative) {
            $destination = rtrim($target, '/') . '/' . $relative;
            $directory = dirname($destination);
            try {
                $files->ensureDirectory($directory);
            } catch (RuntimeException) {
                $output->persist("Could not create directory: {$directory}");
                return 1;
            }

            try {
                $files->copy($source, $destination);
            } catch (RuntimeException) {
                $output->persist("Could not copy file: {$relative}");

                return 1;
            }
        }

        $this->rewriteComposerPath($target, $framework, $files);

        $output->persist("Created Agents starter: {$target}");
        $output->persist('');
        $output->persist('Run it with:');
        $output->persist('  composer install');
        $output->persist('  composer test');

        return 0;
    }

    private function pathOption(CommandContext $ctx, string $option, string $env, string $default): string
    {
        $value = $ctx->options->get($option);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fromContext = $ctx->service(AppContext::class)->get($env);
        if (is_string($fromContext) && $fromContext !== '') {
            return $fromContext;
        }

        return $default;
    }

    private function rewriteComposerPath(string $target, string $framework, ProjectFiles $files): void
    {
        $composerPath = rtrim($target, '/') . '/composer.json';
        if (!$files->fileExists($composerPath)) {
            return;
        }

        $composer = $files->readJson($composerPath);
        if (!is_array($composer)) {
            return;
        }

        $composer['repositories'] = [[
            'type' => 'path',
            'url' => $this->relativePath($target, $framework),
            'options' => [
                'symlink' => true,
                'versions' => [
                    'phalanx-php/phalanx' => self::PHALANX_FRAMEWORK_VERSION,
                ],
            ],
        ]];

        $files->writeJson($composerPath, $composer);
    }

    private function relativePath(string $fromDirectory, string $toDirectory): string
    {
        $from = explode('/', trim((string) realpath($fromDirectory), '/'));
        $to = explode('/', trim((string) realpath($toDirectory), '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return implode('/', [...array_fill(0, count($from), '..'), ...$to]) ?: '.';
    }

    private function files(CommandContext $ctx): ProjectFiles
    {
        return $this->files ??= new PhalanxProjectFiles($ctx->service(Files::class));
    }
}
