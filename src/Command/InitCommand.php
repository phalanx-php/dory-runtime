<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use FilesystemIterator;
use Phalanx\Boot\AppContext;
use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Task\Scopeable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class InitCommand implements Scopeable, DescribesCommand
{
    private const string STARTER_SCRIPT = 'script';

    private const string STARTER_COLLAB = 'collab';

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

        $starter = (string) $ctx->options->get('starter', self::STARTER_SCRIPT);
        if ($starter === self::STARTER_COLLAB) {
            return $this->createCollabStarter($ctx, $absolute, $output);
        }

        if ($starter !== self::STARTER_SCRIPT) {
            $output->persist("Unknown starter: {$starter}");
            $output->persist('Available starters: script, collab');
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
                Opt::value('starter', '', 'Starter template: script or collab', self::STARTER_SCRIPT),
                Opt::value('template-path', '', 'Local Collab starter template path'),
                Opt::value('framework-path', '', 'Local phalanx framework path'),
                Opt::flag('force', 'f', 'Overwrite existing files'),
            ],
            examples: [
                'bia init my-project',
                'bia new . --name=sync.php',
                'bia new my-collab-app --starter=collab',
                'bia init scripts -f',
            ],
            aliases: ['new'],
        );
    }

    private function createCollabStarter(CommandContext $ctx, string $target, StreamOutput $output): int
    {
        $template = $this->pathOption(
            $ctx,
            'template-path',
            'PHALANX_COLLAB_STARTER_PATH',
            dirname(__DIR__, 4) . '/starter-kits/collab-starter',
        );
        $framework = $this->pathOption(
            $ctx,
            'framework-path',
            'PHALANX_FRAMEWORK_PATH',
            dirname(__DIR__, 4) . '/phalanx',
        );

        if (!is_dir($template)) {
            $output->persist("Collab starter template not found: {$template}");
            return 1;
        }

        if (!is_dir($framework)) {
            $output->persist("Phalanx framework path not found: {$framework}");
            return 1;
        }

        $force = $ctx->options->flag('force');
        foreach ($this->templateFiles($template) as $source => $relative) {
            $destination = rtrim($target, '/') . '/' . $relative;
            if (file_exists($destination) && !$force) {
                $output->persist("File already exists: {$destination}");
                $output->persist('Use --force to overwrite.');
                return 0;
            }
        }

        foreach ($this->templateFiles($template) as $source => $relative) {
            $destination = rtrim($target, '/') . '/' . $relative;
            $directory = dirname($destination);
            if (!is_dir($directory) && !mkdir($directory, 0755, recursive: true)) {
                $output->persist("Could not create directory: {$directory}");
                return 1;
            }

            if (!copy($source, $destination)) {
                $output->persist("Could not copy file: {$relative}");

                return 1;
            }
        }

        $this->rewriteComposerPath($target, $framework);

        $output->persist("Created Collab starter: {$target}");
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

    /** @return array<string, string> */
    private function templateFiles(string $template): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($template, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = ltrim(substr($path, strlen(rtrim($template, '/'))), '/');
            $segments = explode('/', str_replace('\\', '/', $relative));
            if (array_intersect($segments, self::EXCLUDED_TEMPLATE_NAMES) !== []) {
                continue;
            }

            if ($file->isFile()) {
                $files[$path] = $relative;
            }
        }

        ksort($files);

        return $files;
    }

    private function rewriteComposerPath(string $target, string $framework): void
    {
        $composerPath = rtrim($target, '/') . '/composer.json';
        if (!is_file($composerPath)) {
            return;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($composer)) {
            return;
        }

        $composer['repositories'] = [[
            'type' => 'path',
            'url' => $this->relativePath($target, $framework),
            'options' => [
                'symlink' => true,
                'versions' => [
                    'phalanx-php/phalanx' => '0.7.x-dev',
                ],
            ],
        ]];

        $written = file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        if ($written === false) {
            throw new RuntimeException("Could not rewrite composer path: {$composerPath}");
        }
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
}
