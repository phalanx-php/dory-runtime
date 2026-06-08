<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use FilesystemIterator;
use Phalanx\Filesystem\Files;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final readonly class PhalanxProjectFiles implements ProjectFiles
{
    public function __construct(
        private Files $files,
    ) {
    }

    public function directoryExists(string $path): bool
    {
        return is_dir($path);
    }

    public function fileExists(string $path): bool
    {
        return $this->files->exists($path);
    }

    public function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        $this->files->mkdir($path);
    }

    public function write(string $path, string $contents): void
    {
        $this->files->write($path, $contents);
    }

    public function copy(string $source, string $destination): void
    {
        $this->files->write($destination, $this->files->read($source));
    }

    public function readJson(string $path): mixed
    {
        return $this->files->readJson($path);
    }

    public function writeJson(string $path, mixed $payload): void
    {
        $this->files->writeJson($path, $payload);
    }

    public function templateFiles(string $template, array $excludedNames): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($template, FilesystemIterator::SKIP_DOTS),
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
}
