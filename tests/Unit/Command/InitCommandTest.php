<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Command;

use FilesystemIterator;
use Phalanx\Bia\Command\InitCommand;
use Phalanx\Console\Command\CommandArgs;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\CommandOptions;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Console\Output\TerminalEnvironment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class InitCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/' . uniqid('bia-init-test-', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function creates_hello_script_in_target_directory(): void
    {
        mkdir($this->tempDir, 0755, recursive: true);

        $scope = $this->buildScope($this->tempDir);
        $command = new InitCommand();
        $result = $command($scope);

        self::assertSame(0, $result);
        self::assertFileExists($this->tempDir . '/hello.php');

        $content = file_get_contents($this->tempDir . '/hello.php');
        self::assertIsString($content);
        self::assertStringContainsString('<?php', $content);
        self::assertStringContainsString('declare(strict_types=1)', $content);
    }

    #[Test]
    public function does_not_overwrite_existing_hello_script(): void
    {
        mkdir($this->tempDir, 0755, recursive: true);
        $existing = 'existing content';
        file_put_contents($this->tempDir . '/hello.php', $existing);

        [$scope, $stream] = $this->buildScopeWithStream($this->tempDir);
        $command = new InitCommand();
        $result = $command($scope);

        self::assertSame(0, $result);

        $persisted = file_get_contents($this->tempDir . '/hello.php');
        self::assertIsString($persisted);
        self::assertSame($existing, $persisted);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertStringContainsString('already exists', $output);
    }

    #[Test]
    public function creates_directory_if_missing(): void
    {
        self::assertDirectoryDoesNotExist($this->tempDir);

        $scope = $this->buildScope($this->tempDir);
        $command = new InitCommand();
        $result = $command($scope);

        self::assertSame(0, $result);
        self::assertDirectoryExists($this->tempDir);
        self::assertFileExists($this->tempDir . '/hello.php');
    }

    #[Test]
    public function returns_zero_on_success(): void
    {
        mkdir($this->tempDir, 0755, recursive: true);

        $scope = $this->buildScope($this->tempDir);
        $command = new InitCommand();
        $result = $command($scope);

        self::assertSame(0, $result);
    }

    #[Test]
    public function creates_collab_starter_from_template_path(): void
    {
        $template = $this->createCollabTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->tempDir . '/project';

        [$scope, $stream] = $this->buildScopeWithStream($target, [
            'starter' => 'collab',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = (new InitCommand())($scope);

        self::assertSame(0, $result);
        self::assertFileExists($target . '/composer.json');
        self::assertFileExists($target . '/app/Collab.php');
        self::assertFileExists($target . '/config/phalanx.toml');
        self::assertFileDoesNotExist($target . '/composer.lock');
        self::assertDirectoryDoesNotExist($target . '/vendor');

        $composer = json_decode((string) file_get_contents($target . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertSame('../phalanx', $composer['repositories'][0]['url']);
        self::assertSame('0.7.x-dev', $composer['repositories'][0]['options']['versions']['phalanx-php/phalanx']);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertStringContainsString('Created Collab starter', $output);
        self::assertStringContainsString('composer test', $output);
    }

    #[Test]
    public function does_not_overwrite_collab_starter_files_without_force(): void
    {
        $template = $this->createCollabTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->tempDir . '/project';
        $existing = '<?php echo "existing";';

        mkdir($target . '/app', 0755, recursive: true);
        file_put_contents($target . '/app/Collab.php', $existing);

        [$scope, $stream] = $this->buildScopeWithStream($target, [
            'starter' => 'collab',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = (new InitCommand())($scope);

        self::assertSame(0, $result);
        self::assertSame($existing, file_get_contents($target . '/app/Collab.php'));
        self::assertFileDoesNotExist($target . '/composer.json');

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertStringContainsString('already exists', $output);
    }

    #[Test]
    public function force_overwrites_collab_starter_files(): void
    {
        $template = $this->createCollabTemplate();
        $framework = $this->createFrameworkPath();
        $target = $this->tempDir . '/project';

        mkdir($target . '/app', 0755, recursive: true);
        file_put_contents($target . '/app/Collab.php', '<?php echo "existing";');

        $scope = $this->buildScope($target, [
            'force' => true,
            'starter' => 'collab',
            'template-path' => $template,
            'framework-path' => $framework,
        ]);

        $result = (new InitCommand())($scope);

        self::assertSame(0, $result);
        self::assertSame("<?php\n\ndeclare(strict_types=1);\n", file_get_contents($target . '/app/Collab.php'));
        self::assertFileExists($target . '/composer.json');
    }

    #[Test]
    public function returns_error_for_unknown_starter(): void
    {
        [$scope, $stream] = $this->buildScopeWithStream($this->tempDir, [
            'starter' => 'unknown',
        ]);

        $result = (new InitCommand())($scope);

        self::assertSame(1, $result);

        rewind($stream);
        $output = stream_get_contents($stream);
        self::assertStringContainsString('Unknown starter: unknown', $output);
        self::assertStringContainsString('Available starters: script, collab', $output);
    }

    /** @param array<string, mixed> $options */
    private function buildScope(string $directory, array $options = []): CommandContext
    {
        [$scope] = $this->buildScopeWithStream($directory, $options);

        return $scope;
    }

    /** @return array{CommandContext, resource} */
    private function buildScopeWithStream(string $directory, array $options = []): array
    {
        $args = new CommandArgs(['directory' => $directory]);
        $options = new CommandOptions($options);
        $stream = fopen('php://memory', 'rw');
        self::assertIsResource($stream);

        $terminal = new TerminalEnvironment(isTty: false);
        $output = new StreamOutput($stream, $terminal);

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

    private function createCollabTemplate(): string
    {
        $template = $this->tempDir . '/template';

        mkdir($template . '/app', 0755, recursive: true);
        mkdir($template . '/config', 0755, recursive: true);
        mkdir($template . '/vendor', 0755, recursive: true);

        file_put_contents($template . '/app/Collab.php', "<?php\n\ndeclare(strict_types=1);\n");
        file_put_contents($template . '/config/phalanx.toml', "[app]\nname = \"collab-starter\"\n");
        file_put_contents($template . '/composer.lock', '{}');
        file_put_contents($template . '/vendor/ignored.php', '<?php');
        file_put_contents(
            $template . '/composer.json',
            json_encode([
                'name' => 'phalanx-php/collab-starter',
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => '../../phalanx',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        return $template;
    }

    private function createFrameworkPath(): string
    {
        $framework = $this->tempDir . '/phalanx';

        mkdir($framework, 0755, recursive: true);

        return $framework;
    }
}
