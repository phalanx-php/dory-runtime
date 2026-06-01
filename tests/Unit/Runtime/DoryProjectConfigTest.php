<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Runtime;

use Phalanx\Dory\Runtime\DoryProjectConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoryProjectConfigTest extends TestCase
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
    public function discover_returns_empty_when_no_composer_json(): void
    {
        $dir = $this->makeTempDir();

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([], $config->contextOverlay());
        self::assertNull($config->projectRoot);
    }

    #[Test]
    public function discover_reads_extra_dory_section(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'timeout' => 120,
            'concurrency' => 25,
            'verbose' => true,
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([
            'DORY_SCRIPT_TIMEOUT' => 120,
            'DORY_MAX_CONCURRENCY' => 25,
            'DORY_VERBOSE' => true,
        ], $config->contextOverlay());
    }

    #[Test]
    public function discover_walks_up_directories(): void
    {
        $parent = $this->makeTempDir();
        $child = $parent . '/sub/deep';
        mkdir($child, 0777, true);

        $this->writeComposerJson($parent, ['timeout' => 90]);

        $config = DoryProjectConfig::discover($child);

        self::assertSame($parent, $config->projectRoot);
        self::assertSame(['DORY_SCRIPT_TIMEOUT' => 90], $config->contextOverlay());
    }

    #[Test]
    public function context_overlay_maps_serve_keys(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'serve' => [
                'host' => '127.0.0.1',
                'port' => 9001,
            ],
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([
            'PHALANX_HOST' => '127.0.0.1',
            'PHALANX_PORT' => 9001,
        ], $config->contextOverlay());
    }

    #[Test]
    public function context_overlay_combines_flat_and_nested_keys(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'timeout' => 60,
            'serve' => ['port' => 9000],
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([
            'DORY_SCRIPT_TIMEOUT' => 60,
            'PHALANX_PORT' => 9000,
        ], $config->contextOverlay());
    }

    #[Test]
    public function context_overlay_skips_unmapped_keys(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'timeout' => 60,
            'custom_thing' => 'ignored',
            'serve' => [
                'port' => 9000,
                'mode' => 'stoa',
            ],
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([
            'DORY_SCRIPT_TIMEOUT' => 60,
            'PHALANX_PORT' => 9000,
        ], $config->contextOverlay());
    }

    #[Test]
    public function section_returns_sub_array(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'serve' => [
                'host' => '0.0.0.0',
                'mode' => 'stoa',
            ],
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([
            'host' => '0.0.0.0',
            'mode' => 'stoa',
        ], $config->section('serve'));
    }

    #[Test]
    public function section_returns_empty_for_missing_key(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, ['timeout' => 30]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([], $config->section('serve'));
        self::assertSame([], $config->section('nonexistent'));
    }

    #[Test]
    public function discover_handles_missing_extra_dory(): void
    {
        $dir = $this->makeTempDir();

        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'test/project',
        ]));

        $config = DoryProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function discover_handles_malformed_json(): void
    {
        $dir = $this->makeTempDir();

        file_put_contents($dir . '/composer.json', '{broken');

        $config = DoryProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function discover_handles_scalar_extra_dory(): void
    {
        $dir = $this->makeTempDir();

        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'test/project',
            'extra' => ['dory' => 'not-an-array'],
        ]));

        $config = DoryProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function section_returns_empty_for_scalar_value(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, ['timeout' => 60]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame([], $config->section('timeout'));
    }

    #[Test]
    public function context_overlay_passes_values_with_json_types(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'timeout' => '120',
            'verbose' => 'true',
        ]);

        $config = DoryProjectConfig::discover($dir);

        self::assertSame('120', $config->contextOverlay()['DORY_SCRIPT_TIMEOUT']);
        self::assertSame('true', $config->contextOverlay()['DORY_VERBOSE']);
    }

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/dory_test_' . uniqid('', true);
        mkdir($dir, 0777, true);

        $this->tempDir = $dir;

        return $dir;
    }

    /** @param array<string, mixed> $doryConfig */
    private function writeComposerJson(string $dir, array $doryConfig): void
    {
        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'test/project',
            'extra' => ['dory' => $doryConfig],
        ]));
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
