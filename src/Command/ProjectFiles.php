<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

interface ProjectFiles
{
    public function directoryExists(string $path): bool;

    public function fileExists(string $path): bool;

    public function ensureDirectory(string $path): void;

    public function write(string $path, string $contents): void;

    public function copy(string $source, string $destination): void;

    public function readJson(string $path): mixed;

    public function writeJson(string $path, mixed $payload): void;

    /**
     * @param list<string> $excludedNames
     * @return array<string, string>
     */
    public function templateFiles(string $template, array $excludedNames): array;
}
