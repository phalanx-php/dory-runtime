<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Runtime;

use Phalanx\Dory\Runtime\DoryConfig;
use Phalanx\Dory\Runtime\DoryProjectConfig;
use Phalanx\Themis\ConfigFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoryConfigPrecedenceTest extends TestCase
{
    private ?string $tempDir = null;

    #[\Override]
    protected function tearDown(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            self::removeDirectory($this->tempDir);
        }
    }

    #[Test]
    public function project_config_supplies_values_when_env_absent(): void
    {
        $project = $this->projectWith(['timeout' => 120, 'concurrency' => 25]);

        $context = $project->contextOverlay();

        $config = ConfigFactory::fromContext($context)->hydrate(DoryConfig::class);

        self::assertSame(120.0, $config->scriptTimeout);
        self::assertSame(25, $config->maxConcurrency);
    }

    #[Test]
    public function env_overrides_project_config(): void
    {
        $project = $this->projectWith(['timeout' => 120]);

        $context = [
            ...$project->contextOverlay(),
            'DORY_SCRIPT_TIMEOUT' => '45',
        ];

        $config = ConfigFactory::fromContext($context)->hydrate(DoryConfig::class);

        self::assertSame(45.0, $config->scriptTimeout);
    }

    #[Test]
    public function env_wins_for_all_dory_config_fields(): void
    {
        $project = $this->projectWith([
            'timeout' => 120,
            'concurrency' => 25,
            'verbose' => false,
        ]);

        $env = [
            'DORY_SCRIPT_TIMEOUT' => '10',
            'DORY_MAX_CONCURRENCY' => '5',
            'DORY_VERBOSE' => 'true',
        ];

        $context = [
            ...$project->contextOverlay(),
            ...$env,
        ];

        $config = ConfigFactory::fromContext($context)->hydrate(DoryConfig::class);

        self::assertSame(10.0, $config->scriptTimeout);
        self::assertSame(5, $config->maxConcurrency);
        self::assertTrue($config->verbose);
    }

    #[Test]
    public function defaults_apply_when_neither_project_nor_env_set(): void
    {
        $project = $this->projectWith([]);

        $config = ConfigFactory::fromContext($project->contextOverlay())
            ->hydrate(DoryConfig::class);

        self::assertSame(30.0, $config->scriptTimeout);
        self::assertSame(50, $config->maxConcurrency);
        self::assertFalse($config->verbose);
    }

    #[Test]
    public function partial_project_config_merges_with_defaults(): void
    {
        $project = $this->projectWith(['timeout' => 90]);

        $config = ConfigFactory::fromContext($project->contextOverlay())
            ->hydrate(DoryConfig::class);

        self::assertSame(90.0, $config->scriptTimeout);
        self::assertSame(50, $config->maxConcurrency);
        self::assertFalse($config->verbose);
    }

    /** @param array<string, mixed> $doryConfig */
    private function projectWith(array $doryConfig): DoryProjectConfig
    {
        $dir = $this->makeTempDir();

        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'test/precedence',
            'extra' => ['dory' => $doryConfig],
        ]));

        return DoryProjectConfig::discover($dir);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/dory_precedence_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $this->tempDir = $dir;

        return $dir;
    }

    private static function removeDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
