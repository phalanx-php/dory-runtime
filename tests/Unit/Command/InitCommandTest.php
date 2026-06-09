<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Command;

use Phalanx\Bia\Command\InitCommand;
use Phalanx\Bia\Command\ProjectFiles;
use Phalanx\Console\Command\CommandArgs;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\CommandOptions;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Console\Output\TerminalEnvironment;
use Phalanx\Stream\ResourceHandle;
use Phalanx\Stream\Stream;
use Phalanx\Testing\TempWorkspace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InitCommandTest extends TestCase
{
    private TempWorkspace $workspace;

    private ProjectFiles $files;

    /** @var list<ResourceHandle> */
    private array $streams = [];

    protected function setUp(): void
    {
        $this->workspace = TempWorkspace::create('bia-init-test-');
        $this->files = new TestProjectFiles($this->workspace);
    }

    protected function tearDown(): void
    {
        $this->workspace->cleanup();
    }

    #[Test]
    public function creates_hello_script_in_target_directory(): void
    {
        $target = $this->workspace->dir('target');

        $scope = $this->buildScope($target);
        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertFileExists($target . '/hello.php');

        $content = $this->workspace->read('target/hello.php');
        self::assertStringContainsString('<?php', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
    }

    #[Test]
    public function does_not_overwrite_existing_hello_script(): void
    {
        $target = $this->workspace->dir('target');
        $existing = 'existing content';
        $this->workspace->file('target/hello.php', $existing);

        [$scope, $stream] = $this->buildScopeWithStream($target);
        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertSame($existing, $this->workspace->read('target/hello.php'));

        $output = $stream->contents();
        self::assertStringContainsString('already exists', $output);
    }

    #[Test]
    public function creates_directory_if_missing(): void
    {
        $target = $this->workspace->missingPath('created');
        self::assertDirectoryDoesNotExist($target);

        $scope = $this->buildScope($target);
        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertDirectoryExists($target);
        self::assertFileExists($target . '/hello.php');
    }

    #[Test]
    public function returns_zero_on_success(): void
    {
        $target = $this->workspace->dir('target');

        $scope = $this->buildScope($target);
        $result = $this->command()($scope);

        self::assertSame(0, $result);
    }

    #[Test]
    public function creates_agent_harness_starter_from_template_path(): void
    {
        $template = $this->createAgentHarnessTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->workspace->path('project');

        [$scope, $stream] = $this->buildScopeWithStream($target, [
            'starter' => 'agent-harness',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertFileExists($target . '/composer.json');
        self::assertFileExists($target . '/app/AgentHarness.php');
        self::assertFileExists($target . '/config/phalanx.toml');
        self::assertFileDoesNotExist($target . '/composer.lock');
        self::assertDirectoryDoesNotExist($target . '/vendor');

        $composer = json_decode($this->workspace->read('project/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('../phalanx', $composer['repositories'][0]['url']);
        self::assertSame('2.0.x-dev', $composer['repositories'][0]['options']['versions']['phalanx-php/phalanx']);

        $output = $stream->contents();
        self::assertStringContainsString('Created Agents starter', $output);
        self::assertStringContainsString('composer test', $output);
    }

    #[Test]
    public function does_not_overwrite_agent_harness_starter_files_without_force(): void
    {
        $template = $this->createAgentHarnessTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->workspace->path('project');
        $existing = '<?php echo "existing";';

        $this->workspace->file('project/app/AgentHarness.php', $existing);

        [$scope, $stream] = $this->buildScopeWithStream($target, [
            'starter' => 'agent-harness',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertSame($existing, $this->workspace->read('project/app/AgentHarness.php'));
        self::assertFileDoesNotExist($target . '/composer.json');

        $output = $stream->contents();
        self::assertStringContainsString('already exists', $output);
    }

    #[Test]
    public function force_overwrites_agent_harness_starter_files(): void
    {
        $template = $this->createAgentHarnessTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->workspace->path('project');

        $this->workspace->file('project/app/AgentHarness.php', '<?php echo "existing";');

        $scope = $this->buildScope($target, [
            'force' => true,
            'starter' => 'agent-harness',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = $this->command()($scope);

        self::assertSame(0, $result);
        self::assertSame("<?php\n\ndeclare(strict_types=1);\n", $this->workspace->read('project/app/AgentHarness.php'));
        self::assertFileExists($target . '/composer.json');
    }

    #[Test]
    public function returns_error_for_unknown_starter(): void
    {
        [$scope, $stream] = $this->buildScopeWithStream($this->workspace->path('target'), [
            'starter' => 'unknown',
        ]);

        $result = $this->command()($scope);

        self::assertSame(1, $result);

        $output = $stream->contents();
        self::assertStringContainsString('Unknown starter: unknown', $output);
        self::assertStringContainsString('Available starters: script, agent-harness', $output);
    }

    /** @param array<string, mixed> $options */
    private function buildScope(string $directory, array $options = []): CommandContext
    {
        [$scope] = $this->buildScopeWithStream($directory, $options);

        return $scope;
    }

    /** @return array{CommandContext, ResourceHandle} */
    private function buildScopeWithStream(string $directory, array $options = []): array
    {
        $args = new CommandArgs(['directory' => $directory]);
        $options = new CommandOptions($options);
        $stream = Stream::captureBuffer();
        $this->streams[] = $stream;

        $terminal = new TerminalEnvironment(isTty: false);
        $output = new StreamOutput($stream->resource(), $terminal);

        $scope = $this->createStub(CommandContext::class);
        $scope->method('$args::get')->willReturn($args);
        $scope->method('$options::get')->willReturn($options);
        $scope->method('service')->willReturnCallback(
            static fn(string $type) => match ($type) {
                StreamOutput::class => $output,
                default => throw new RuntimeException('Unexpected service: ' . $type),
            },
        );

        return [$scope, $stream];
    }

    private function createAgentHarnessTemplate(): string
    {
        $this->workspace->file('template/app/AgentHarness.php', "<?php\n\ndeclare(strict_types=1);\n");
        $this->workspace->file('template/config/phalanx.toml', "[app]\nname = \"agents\"\n");
        $this->workspace->file('template/composer.lock', '{}');
        $this->workspace->file('template/vendor/ignored.php', '<?php');
        $this->workspace->file('template/composer.json', json_encode([
            'name' => 'phalanx-php/agents-starter',
            'repositories' => [
                [
                    'type' => 'path',
                    'url' => '../../phalanx',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return $this->workspace->path('template');
    }

    private function createFrameworkPath(): string
    {
        return $this->workspace->dir('phalanx');
    }

    private function command(): InitCommand
    {
        return new InitCommand($this->files);
    }
}

final class TestProjectFiles implements ProjectFiles
{
    public function __construct(
        private readonly TempWorkspace $workspace,
    ) {
    }

    public function directoryExists(string $path): bool
    {
        return is_dir($path);
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function ensureDirectory(string $path): void
    {
        $this->workspace->dir($this->relative($path));
    }

    public function write(string $path, string $contents): void
    {
        $this->workspace->file($this->relative($path), $contents);
    }

    public function copy(string $source, string $destination): void
    {
        $this->write($destination, $this->workspace->readPath($source));
    }

    public function readJson(string $path): mixed
    {
        return json_decode($this->workspace->readPath($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function writeJson(string $path, mixed $payload): void
    {
        $this->write($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    public function templateFiles(string $template, array $excludedNames): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($template, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = ltrim(substr($path, strlen(rtrim($template, '/'))), '/');
            $segments = explode('/', str_replace('\\', '/', $relative));
            if (array_intersect($segments, $excludedNames) !== []) {
                continue;
            }

            if ($file->isFile()) {
                $files[$path] = $relative;
            }
        }

        ksort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        $root = rtrim($this->workspace->root, DIRECTORY_SEPARATOR);
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);

        if ($path === $root) {
            return '';
        }

        if (!str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("Path is outside temporary workspace: {$path}");
        }

        return substr($path, strlen($root) + 1);
    }
}
