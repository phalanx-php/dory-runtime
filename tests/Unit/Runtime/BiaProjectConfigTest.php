<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Runtime\BiaProjectConfig;
use Phalanx\Bia\Tests\Fixtures\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaProjectConfigTest extends TestCase
{
    use TemporaryDirectoryTrait;

    #[\Override]
    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    #[Test]
    public function discover_returns_empty_when_no_composer_json(): void
    {
        $dir = $this->makeTempDir();

        $config = BiaProjectConfig::discover($dir);

        self::assertSame([], $config->contextOverlay());
        self::assertNull($config->projectRoot);
    }

    #[Test]
    public function discover_reads_extra_bia_section(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, [
            'timeout' => 120,
            'concurrency' => 25,
            'verbose' => true,
        ]);

        $config = BiaProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([
            'BIA_SCRIPT_TIMEOUT' => 120,
            'BIA_MAX_CONCURRENCY' => 25,
            'BIA_VERBOSE' => true,
        ], $config->contextOverlay());
    }

    #[Test]
    public function discover_walks_up_directories(): void
    {
        $parent = $this->makeTempDir();
        $child = dirname($this->writeTempFile('sub/deep/.keep', ''));

        $this->writeComposerJson($parent, ['timeout' => 90]);

        $config = BiaProjectConfig::discover($child);

        self::assertSame($parent, $config->projectRoot);
        self::assertSame(['BIA_SCRIPT_TIMEOUT' => 90], $config->contextOverlay());
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

        $config = BiaProjectConfig::discover($dir);

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

        $config = BiaProjectConfig::discover($dir);

        self::assertSame([
            'BIA_SCRIPT_TIMEOUT' => 60,
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
                'mode' => 'http',
            ],
        ]);

        $config = BiaProjectConfig::discover($dir);

        self::assertSame([
            'BIA_SCRIPT_TIMEOUT' => 60,
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
                'mode' => 'http',
            ],
        ]);

        $config = BiaProjectConfig::discover($dir);

        self::assertSame([
            'host' => '0.0.0.0',
            'mode' => 'http',
        ], $config->section('serve'));
    }

    #[Test]
    public function section_returns_empty_for_missing_key(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, ['timeout' => 30]);

        $config = BiaProjectConfig::discover($dir);

        self::assertSame([], $config->section('serve'));
        self::assertSame([], $config->section('nonexistent'));
    }

    #[Test]
    public function discover_handles_missing_extra_bia(): void
    {
        $dir = $this->makeTempDir();

        $this->writeTempFile('composer.json', json_encode([
            'name' => 'test/project',
        ]));

        $config = BiaProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function discover_handles_malformed_json(): void
    {
        $dir = $this->makeTempDir();

        $this->writeTempFile('composer.json', '{broken');

        $config = BiaProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function discover_handles_scalar_extra_bia(): void
    {
        $dir = $this->makeTempDir();

        $this->writeTempFile('composer.json', json_encode([
            'name' => 'test/project',
            'extra' => ['bia' => 'not-an-array'],
        ]));

        $config = BiaProjectConfig::discover($dir);

        self::assertSame($dir, $config->projectRoot);
        self::assertSame([], $config->contextOverlay());
    }

    #[Test]
    public function section_returns_empty_for_scalar_value(): void
    {
        $dir = $this->makeTempDir();

        $this->writeComposerJson($dir, ['timeout' => 60]);

        $config = BiaProjectConfig::discover($dir);

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

        $config = BiaProjectConfig::discover($dir);

        self::assertSame('120', $config->contextOverlay()['BIA_SCRIPT_TIMEOUT']);
        self::assertSame('true', $config->contextOverlay()['BIA_VERBOSE']);
    }

    /** @param array<string, mixed> $biaConfig */
    private function writeComposerJson(string $dir, array $biaConfig): void
    {
        $this->writeTempFile('composer.json', json_encode([
            'name' => 'test/project',
            'extra' => ['bia' => $biaConfig],
        ]));
    }
}
