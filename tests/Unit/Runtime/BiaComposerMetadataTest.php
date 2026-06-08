<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Testing\FixtureFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaComposerMetadataTest extends TestCase
{
    #[Test]
    public function local_path_repository_versions_cover_required_phalanx_packages(): void
    {
        $composer = json_decode(
            FixtureFile::read(dirname(__DIR__, 3) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);

        $required = array_filter(
            array_keys($composer['require'] ?? []),
            static fn(string $package): bool => str_starts_with($package, 'phalanx-php/'),
        );

        $versions = $composer['repositories'][0]['options']['versions'] ?? [];
        self::assertIsArray($versions);

        sort($required);
        $versioned = array_keys($versions);
        sort($versioned);

        self::assertSame($required, $versioned);
    }
}
