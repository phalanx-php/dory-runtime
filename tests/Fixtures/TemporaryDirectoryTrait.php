<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Fixtures;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

trait TemporaryDirectoryTrait
{
    private ?string $tempDir = null;

    protected function tearDownTemporaryDirectory(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            self::removeDirectory($this->tempDir);
        }
    }

    private function makeTempDir(string $prefix = 'bia_test_'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . uniqid('', true);
        mkdir($dir, 0777, true);

        $this->tempDir = $dir;

        return $dir;
    }

    private static function removeDirectory(string $dir): void
    {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
