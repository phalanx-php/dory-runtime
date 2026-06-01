<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

use Closure;
use JsonException;
use RuntimeException;

class NativeCodeParser implements CodeParser
{
    /**
     * @param (Closure(string, ?string=): string)|null $parseSource
     * @param (Closure(string): string)|null $parseFile
     */
    public function __construct(
        private ?Closure $parseSource = null,
        private ?Closure $parseFile = null,
    ) {
    }

    public function parseSource(string $source, ?string $name = null): ParseResult
    {
        $call = $this->parseSource ?? self::nativeSourceParser();

        return ParseResult::fromArray(self::payload($call($source, $name)));
    }

    public function parseFile(string $path): ParseResult
    {
        $call = $this->parseFile ?? self::nativeFileParser();

        return ParseResult::fromArray(self::payload($call($path)));
    }

    /** @return Closure(string, ?string=): string */
    private static function nativeSourceParser(): Closure
    {
        if (!function_exists('dory_code_parse_json')) {
            throw new RuntimeException('Dory code parser is not available in this runtime.');
        }

        return static fn(string $source, ?string $name = null): string => dory_code_parse_json($source, $name);
    }

    /** @return Closure(string): string */
    private static function nativeFileParser(): Closure
    {
        if (!function_exists('dory_code_parse_file_json')) {
            throw new RuntimeException('Dory code parser is not available in this runtime.');
        }

        return static fn(string $path): string => dory_code_parse_file_json($path);
    }

    /** @return array<string, mixed> */
    private static function payload(string $json): array
    {
        try {
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidCodePayload::json();
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw InvalidCodePayload::json();
        }

        if (PayloadReader::bool($payload, 'ok') !== true) {
            $message = $payload['message'] ?? null;

            throw new RuntimeException(is_string($message) ? $message : 'Dory code parser failed.');
        }

        return $payload;
    }
}
