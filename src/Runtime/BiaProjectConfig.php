<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

class BiaProjectConfig
{
    private const array KEY_MAP = [
        'timeout' => 'BIA_SCRIPT_TIMEOUT',
        'concurrency' => 'BIA_MAX_CONCURRENCY',
        'verbose' => 'BIA_VERBOSE',
    ];

    private const array SERVE_KEY_MAP = [
        'host' => 'PHALANX_HOST',
        'port' => 'PHALANX_PORT',
    ];

    /** @param array<string, mixed> $values */
    public function __construct(
        private array $values,
        private(set) ?string $projectRoot,
    ) {
    }

    public static function discover(string $startDir): self
    {
        $dir = $startDir;

        while (true) {
            $path = $dir . '/composer.json';

            if (is_file($path)) {
                $json = json_decode((string) file_get_contents($path), true);
                $bia = is_array($json) ? ($json['extra']['bia'] ?? []) : [];

                return new self(is_array($bia) ? $bia : [], $dir);
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                return new self([], null);
            }

            $dir = $parent;
        }
    }

    /** @return array<string, mixed> */
    public function contextOverlay(): array
    {
        $overlay = [];

        foreach (self::KEY_MAP as $jsonKey => $envKey) {
            if (array_key_exists($jsonKey, $this->values)) {
                $overlay[$envKey] = $this->values[$jsonKey];
            }
        }

        $serve = $this->section('serve');

        foreach (self::SERVE_KEY_MAP as $jsonKey => $envKey) {
            if (array_key_exists($jsonKey, $serve)) {
                $overlay[$envKey] = $serve[$jsonKey];
            }
        }

        return $overlay;
    }

    /** @return array<string, mixed> */
    public function section(string $key): array
    {
        $value = $this->values[$key] ?? [];

        return is_array($value) ? $value : [];
    }
}
