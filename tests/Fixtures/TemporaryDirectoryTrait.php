<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Fixtures;

use Phalanx\Testing\TempWorkspace;

trait TemporaryDirectoryTrait
{
    private ?TempWorkspace $tempWorkspace = null;

    protected function tearDownTemporaryDirectory(): void
    {
        $this->tempWorkspace?->cleanup();
    }

    private function makeTempDir(string $prefix = 'bia_test_'): string
    {
        $this->tempWorkspace ??= TempWorkspace::create($prefix);

        return $this->tempWorkspace->root;
    }

    private function tempPath(string $relative): string
    {
        $this->tempWorkspace ??= TempWorkspace::create();

        return $this->tempWorkspace->path($relative);
    }

    private function writeTempFile(string $relative, string $contents): string
    {
        $this->tempWorkspace ??= TempWorkspace::create();

        return $this->tempWorkspace->file($relative, $contents);
    }

    private function readTempFile(string $relative): string
    {
        $this->tempWorkspace ??= TempWorkspace::create();

        return $this->tempWorkspace->read($relative);
    }
}
