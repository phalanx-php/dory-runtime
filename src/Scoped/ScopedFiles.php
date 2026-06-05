<?php

declare(strict_types=1);

namespace Phalanx\Bia\Scoped;

use Phalanx\Filesystem\FileInfo;
use Phalanx\Filesystem\Files;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Stream\Emitter;

class ScopedFiles
{
    private ?Files $files = null;

    public function __construct(private ExecutionScope $ctx)
    {
    }

    public function read(string $path): string
    {
        return $this->resolve()->read($path);
    }

    public function readJson(string $path, bool $assoc = true): mixed
    {
        return $this->resolve()->readJson($path, $assoc);
    }

    public function write(string $path, string $contents): void
    {
        $this->resolve()->write($path, $contents);
    }

    public function writeJson(string $path, mixed $data, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR): void
    {
        $this->resolve()->writeJson($path, $data, $flags);
    }

    public function append(string $path, string $contents): void
    {
        $this->resolve()->append($path, $contents);
    }

    public function readStream(string $path): Emitter
    {
        return $this->resolve()->readStream($path);
    }

    public function writeStream(string $path, Emitter $source): void
    {
        $this->resolve()->writeStream($path, $source);
    }

    public function stat(string $path): FileInfo
    {
        return $this->resolve()->stat($path);
    }

    public function exists(string $path): bool
    {
        return $this->resolve()->exists($path);
    }

    public function delete(string $path): void
    {
        $this->resolve()->delete($path);
    }

    public function move(string $from, string $to): void
    {
        $this->resolve()->move($from, $to);
    }

    public function mkdir(string $path, bool $recursive = true, int $permissions = 0755): void
    {
        $this->resolve()->mkdir($path, $recursive, $permissions);
    }

    /** @return list<string> */
    public function listDir(string $path): array
    {
        return $this->resolve()->listDir($path);
    }

    private function resolve(): Files
    {
        return $this->files ??= $this->ctx->service(Files::class);
    }
}
