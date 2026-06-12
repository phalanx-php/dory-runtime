<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bootstrap\BootstrapContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaComposerMetadataTest extends TestCase
{
    #[Test]
    public function local_path_repository_versions_the_active_phalanx_package(): void
    {
        $composer = self::composer();
        $repository = $composer['repositories'][0] ?? null;

        self::assertIsArray($repository);
        self::assertSame(BootstrapContract::PACKAGE, self::phalanxPackage($composer));
        self::assertSame('../../phalanx', $repository['url']);
        self::assertTrue($repository['options']['symlink']);
        $versions = $repository['options']['versions'] ?? [];
        self::assertIsArray($versions);
        self::assertSame([BootstrapContract::PACKAGE => '2.0.x-dev'], $versions);
    }

    #[Test]
    public function publish_metadata_uses_released_package_constraints_without_local_repositories(): void
    {
        $composer = self::generatedPublishComposer();

        self::assertArrayNotHasKey('repositories', $composer);

        self::assertSame(self::publishConstraint($composer), $composer['require'][BootstrapContract::PACKAGE]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $composer = json_decode(self::read(dirname(__DIR__, 3) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);

        return $composer;
    }

    /**
     * @return array<string, mixed>
     */
    private static function generatedPublishComposer(): array
    {
        $lines = [];
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/tools/release-composer.php') . ' --stdout',
            $lines,
            $exitCode,
        );
        self::assertSame(0, $exitCode);

        $composer = json_decode(implode("\n", $lines), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        return $composer;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private static function branchAlias(array $composer): string
    {
        $alias = $composer['extra']['branch-alias']['dev-main'] ?? null;
        self::assertIsString($alias);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.x-dev$/', $alias);

        return $alias;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private static function publishConstraint(array $composer): string
    {
        $alias = self::branchAlias($composer);

        return '^' . str_replace('.x-dev', '', $alias);
    }

    /**
     * @param array<string, mixed> $composer
     */
    private static function phalanxPackage(array $composer): string
    {
        $packages = [];
        foreach (array_keys($composer['require'] ?? []) as $package) {
            if (is_string($package) && str_starts_with($package, 'phalanx-php/')) {
                $packages[] = $package;
            }
        }

        self::assertCount(1, $packages);

        return $packages[0];
    }

    private static function read(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
