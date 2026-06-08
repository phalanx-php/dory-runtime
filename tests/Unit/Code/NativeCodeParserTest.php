<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Code;

use Phalanx\Bia\Code\DeclarationQuery;
use Phalanx\Bia\Code\InvalidCodePayload;
use Phalanx\Bia\Code\NativeCodeParser;
use Phalanx\Bia\Code\NodeQuery;
use Phalanx\Bia\Code\ParseResult;
use Phalanx\Bia\Code\ReferenceQuery;
use Phalanx\Bia\Code\TokenQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NativeCodeParserTest extends TestCase
{
    #[Test]
    public function parse_source_hydrates_code_payload(): void
    {
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertSame(bin2hex('final class Example {}'), $request['source_hex']);
                self::assertArrayNotHasKey('source', $request);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->parseSource('final class Example {}', 'example.php');

        self::assertInstanceOf(ParseResult::class, $result);
        self::assertSame('example.php', $result->file->name);
        self::assertFalse($result->hasErrors);
        self::assertSame('Class', $result->tokens[0]->kind);
        self::assertSame('class', $result->declarations[0]->kind);
        self::assertSame('Example', $result->declarations[0]->name);
        self::assertSame('App\\Example', $result->declarations[0]->fqn);
    }

    #[Test]
    public function parse_source_request_is_byte_safe(): void
    {
        $source = "final class Example {}\xFF";
        $parser = new NativeCodeParser(
            dispatch: static function (array $request) use ($source): string {
                self::assertSame(bin2hex($source), $request['source_hex']);

                return self::dispatchJson($request);
            },
        );

        self::assertSame('Example', $parser->parseSource($source, 'example.php')->declarations[0]->name);
    }

    #[Test]
    public function parse_file_builds_file_request(): void
    {
        $path = 'bia-runtime-example.php';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request) use ($path): string {
                self::assertSame(['op' => 'parse_file', 'path' => $path], $request);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->parseFile($path);

        self::assertSame($path, $result->file->name);
    }

    #[Test]
    public function index_project_hydrates_project_summary(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static fn (array $request): string => self::dispatchJson($request),
        );

        $index = $parser->indexProject($root);

        self::assertSame($root, $index->root);
        self::assertSame(1, $index->fileCount);
        self::assertSame(1, $index->declarationCount);
        self::assertSame(1, $index->tokenCount);
        self::assertSame(1, $index->nodeCount);
        self::assertSame(1, $index->referenceCount);
        self::assertSame('src/Example.php', $index->files[0]->name);
    }

    #[Test]
    public function declaration_queries_are_typed_and_filterable(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertSame('query_declarations', $request['op']);
                self::assertSame(['kind' => 'class', 'name' => 'Example'], $request['query']);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->queryDeclarations($root, new DeclarationQuery(kind: 'class', name: 'Example'));

        self::assertSame($root, $result->root);
        self::assertSame('src/Example.php', $result->declarations[0]->file);
        self::assertSame('Example', $result->declarations[0]->name);
    }

    #[Test]
    public function token_queries_are_typed_and_filterable(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertSame('query_tokens', $request['op']);
                self::assertSame(['text' => 'class'], $request['query']);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->queryTokens($root, new TokenQuery(text: 'class'));

        self::assertSame($root, $result->root);
        self::assertSame('src/Example.php', $result->tokens[0]->file);
        self::assertSame('Class', $result->tokens[0]->kind);
        self::assertSame('class', $result->tokens[0]->text);
    }

    #[Test]
    public function node_queries_are_typed_and_filterable(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertSame('query_nodes', $request['op']);
                self::assertSame(['kind' => 'method', 'name' => 'run'], $request['query']);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->queryNodes($root, new NodeQuery(kind: 'method', name: 'run'));

        self::assertSame($root, $result->root);
        self::assertSame('src/Example.php', $result->nodes[0]->file);
        self::assertSame('method', $result->nodes[0]->kind);
        self::assertSame('run', $result->nodes[0]->name);
    }

    #[Test]
    public function reference_queries_are_typed_and_filterable(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertSame('query_references', $request['op']);
                self::assertSame([
                    'kind' => 'method',
                    'name' => 'name',
                    'receiver' => '$this',
                ], $request['query']);

                return self::dispatchJson($request);
            },
        );

        $result = $parser->queryReferences(
            $root,
            new ReferenceQuery(
                kind: 'method',
                name: 'name',
                receiver: '$this',
            ),
        );

        self::assertSame($root, $result->root);
        self::assertSame('src/Example.php', $result->references[0]->file);
        self::assertSame('method', $result->references[0]->kind);
        self::assertSame('name', $result->references[0]->name);
        self::assertSame('$this', $result->references[0]->receiver);
    }

    #[Test]
    public function empty_queries_are_encoded_as_empty_filter_objects(): void
    {
        $root = 'bia-runtime-project';
        $parser = new NativeCodeParser(
            dispatch: static function (array $request): string {
                self::assertIsObject($request['query']);
                self::assertSame([], get_object_vars($request['query']));

                return self::dispatchJson($request);
            },
        );

        $parser->queryTokens($root);
    }

    #[Test]
    public function parser_error_payload_throws_runtime_exception(): void
    {
        $parser = new NativeCodeParser(
            dispatch: static fn (): string => json_encode([
                'ok' => false,
                'message' => 'parser unavailable',
            ], JSON_THROW_ON_ERROR),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('parser unavailable');

        $parser->parseSource('broken');
    }

    #[Test]
    public function invalid_json_throws_payload_exception(): void
    {
        $parser = new NativeCodeParser(dispatch: static fn (): string => '{');

        $this->expectException(InvalidCodePayload::class);

        $parser->parseSource('broken');
    }

    #[Test]
    public function missing_payload_shape_throws_payload_exception(): void
    {
        $parser = new NativeCodeParser(
            dispatch: static fn (): string => json_encode([
                'ok' => true,
                'has_errors' => false,
                'errors' => [],
                'tokens' => [],
                'declarations' => [],
            ], JSON_THROW_ON_ERROR),
        );

        $this->expectException(InvalidCodePayload::class);

        $parser->parseSource('broken');
    }

    /** @param array<string, mixed> $request */
    private static function dispatchJson(array $request): string
    {
        return match ($request['op']) {
            'parse_source' => self::payloadJson(($request['name'] ?? null) ?: 'inline.php'),
            'parse_file' => self::payloadJson($request['path']),
            'index_project' => json_encode(self::projectPayload($request['root']), JSON_THROW_ON_ERROR),
            'query_declarations' => json_encode(self::declarationQueryPayload($request['root'], self::queryArray($request)), JSON_THROW_ON_ERROR),
            'query_tokens' => json_encode(self::tokenQueryPayload($request['root'], self::queryArray($request)), JSON_THROW_ON_ERROR),
            'query_nodes' => json_encode(self::nodeQueryPayload($request['root'], self::queryArray($request)), JSON_THROW_ON_ERROR),
            'query_references' => json_encode(self::referenceQueryPayload($request['root'], self::queryArray($request)), JSON_THROW_ON_ERROR),
            default => json_encode(['ok' => false, 'message' => 'unknown query'], JSON_THROW_ON_ERROR),
        };
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, string>
     */
    private static function queryArray(array $request): array
    {
        $query = $request['query'] ?? [];

        if (is_object($query)) {
            $query = get_object_vars($query);
        }

        self::assertIsArray($query);

        $result = [];
        foreach ($query as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);
            $result[$key] = $value;
        }

        return $result;
    }

    private static function payloadJson(string $name): string
    {
        return json_encode(self::payload($name), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function projectPayload(string $root): array
    {
        return [
            'ok' => true,
            'root' => $root,
            'files' => [[
                'id' => '1',
                'name' => 'src/Example.php',
                'wrapped' => false,
            ]],
            'file_count' => 1,
            'declaration_count' => 1,
            'token_count' => 1,
            'node_count' => 1,
            'reference_count' => 1,
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private static function declarationQueryPayload(string $root, array $query): array
    {
        $payload = self::payload('src/Example.php');
        $declaration = $payload['declarations'][0];
        $declaration['file'] = 'src/Example.php';
        $matches = ($query['kind'] ?? $declaration['kind']) === $declaration['kind']
            && ($query['name'] ?? $declaration['name']) === $declaration['name']
            && ($query['fqn'] ?? $declaration['fqn']) === $declaration['fqn']
            && ($query['file'] ?? $declaration['file']) === $declaration['file'];

        return [
            'ok' => true,
            'root' => $root,
            'declarations' => $matches ? [$declaration] : [],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private static function tokenQueryPayload(string $root, array $query): array
    {
        $payload = self::payload('src/Example.php');
        $token = $payload['tokens'][0];
        $token['file'] = 'src/Example.php';
        $matches = ($query['kind'] ?? $token['kind']) === $token['kind']
            && ($query['text'] ?? $token['text']) === $token['text']
            && ($query['file'] ?? $token['file']) === $token['file'];

        return [
            'ok' => true,
            'root' => $root,
            'tokens' => $matches ? [$token] : [],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private static function nodeQueryPayload(string $root, array $query): array
    {
        $payload = self::payload('src/Example.php');
        $node = $payload['nodes'][0];
        $node['file'] = 'src/Example.php';
        $matches = ($query['kind'] ?? $node['kind']) === $node['kind']
            && ($query['name'] ?? $node['name']) === $node['name']
            && ($query['file'] ?? $node['file']) === $node['file']
            && ($query['context'] ?? $node['context']) === $node['context'];

        return [
            'ok' => true,
            'root' => $root,
            'nodes' => $matches ? [$node] : [],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private static function referenceQueryPayload(string $root, array $query): array
    {
        $payload = self::payload('src/Example.php');
        $reference = $payload['references'][0];
        $reference['file'] = 'src/Example.php';
        $matches = ($query['kind'] ?? $reference['kind']) === $reference['kind']
            && ($query['name'] ?? $reference['name']) === $reference['name']
            && ($query['receiver'] ?? $reference['receiver']) === $reference['receiver']
            && ($query['file'] ?? $reference['file']) === $reference['file']
            && ($query['context'] ?? $reference['context']) === $reference['context'];

        return [
            'ok' => true,
            'root' => $root,
            'references' => $matches ? [$reference] : [],
            'errors' => [],
        ];
    }

    /** @return array<string, mixed> */
    private static function payload(string $name = 'example.php'): array
    {
        return [
            'ok' => true,
            'file' => [
                'id' => '1',
                'name' => $name,
                'wrapped' => false,
            ],
            'has_errors' => false,
            'errors' => [],
            'tokens' => [[
                'kind' => 'Class',
                'text' => 'class',
                'span' => self::span(),
            ]],
            'declarations' => [[
                'kind' => 'class',
                'name' => 'Example',
                'namespace' => 'App',
                'declaring_type' => null,
                'fqn' => 'App\\Example',
                'span' => self::span(),
                'name_span' => self::span(),
            ]],
            'nodes' => [[
                'kind' => 'method',
                'name' => 'run',
                'span' => self::span(),
                'context' => 'App\\Example',
            ]],
            'references' => [[
                'kind' => 'method',
                'name' => 'name',
                'receiver' => '$this',
                'span' => self::span(),
                'context' => 'App\\Example::run',
            ]],
        ];
    }

    /** @return array<string, int> */
    private static function span(): array
    {
        return [
            'start_offset' => 0,
            'end_offset' => 5,
            'start_line' => 1,
            'start_column' => 1,
            'end_line' => 1,
            'end_column' => 6,
        ];
    }
}
