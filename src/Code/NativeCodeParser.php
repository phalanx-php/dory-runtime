<?php

declare(strict_types=1);

namespace Phalanx\Bia\Code;

use Closure;
use JsonException;
use RuntimeException;

class NativeCodeParser implements CodeParser
{
    /** @param (Closure(array<string, mixed>): string)|null $dispatch */
    public function __construct(
        private ?Closure $dispatch = null,
    ) {
    }

    public function parseSource(string $source, ?string $name = null): ParseResult
    {
        $request = [
            'op' => 'parse_source',
            'source_hex' => bin2hex($source),
        ];

        if ($name !== null) {
            $request['name'] = $name;
        }

        return ParseResult::fromArray($this->payloadFor($request));
    }

    public function parseFile(string $path): ParseResult
    {
        return ParseResult::fromArray($this->payloadFor([
            'op' => 'parse_file',
            'path' => $path,
        ]));
    }

    public function indexProject(string $root): CodeProjectIndex
    {
        return CodeProjectIndex::fromArray($this->payloadFor([
            'op' => 'index_project',
            'root' => $root,
        ]));
    }

    public function queryDeclarations(string $root, ?DeclarationQuery $query = null): DeclarationQueryResult
    {
        return DeclarationQueryResult::fromArray($this->payloadFor([
            'op' => 'query_declarations',
            'root' => $root,
            'query' => self::queryPayload(($query ?? new DeclarationQuery())->toArray()),
        ]));
    }

    public function queryTokens(string $root, ?TokenQuery $query = null): TokenQueryResult
    {
        return TokenQueryResult::fromArray($this->payloadFor([
            'op' => 'query_tokens',
            'root' => $root,
            'query' => self::queryPayload(($query ?? new TokenQuery())->toArray()),
        ]));
    }

    public function queryNodes(string $root, ?NodeQuery $query = null): NodeQueryResult
    {
        return NodeQueryResult::fromArray($this->payloadFor([
            'op' => 'query_nodes',
            'root' => $root,
            'query' => self::queryPayload(($query ?? new NodeQuery())->toArray()),
        ]));
    }

    public function queryReferences(string $root, ?ReferenceQuery $query = null): ReferenceQueryResult
    {
        return ReferenceQueryResult::fromArray($this->payloadFor([
            'op' => 'query_references',
            'root' => $root,
            'query' => self::queryPayload(($query ?? new ReferenceQuery())->toArray()),
        ]));
    }

    /**
     * @param array<string, string> $query
     * @return array<string, string>|object
     */
    private static function queryPayload(array $query): array|object
    {
        if ($query === []) {
            return (object) [];
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    private function payloadFor(array $request): array
    {
        $dispatch = $this->dispatch ?? self::nativeQueryDispatcher();

        return self::payload($dispatch($request));
    }

    /** @return Closure(array<string, mixed>): string */
    private static function nativeQueryDispatcher(): Closure
    {
        if (!function_exists('bia_code_query_json')) {
            throw new RuntimeException('Bia code parser is not available in this runtime.');
        }

        return static fn (array $request): string => bia_code_query_json(json_encode($request, JSON_THROW_ON_ERROR));
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

            throw new RuntimeException(is_string($message) ? $message : 'Bia code parser failed.');
        }

        return $payload;
    }
}
