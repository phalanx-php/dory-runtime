<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Scoped;

use Phalanx\Dory\Code\DeclarationIndex;
use Phalanx\Dory\Code\CodeParser;
use Phalanx\Dory\Code\InvalidCodePayload;
use Phalanx\Dory\Code\NativeCodeParser;
use Phalanx\Dory\Code\ParseResult;
use Phalanx\Dory\Code\TokenIndex;
use Phalanx\Dory\Scoped\ScopedCode;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScopedCodeTest extends TestCase
{
    #[Test]
    public function parse_hydrates_code_payload(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseSource: static fn(string $source, ?string $name = null): string => self::payloadJson($name ?? 'inline.php'),
            ),
        );

        $result = $code->parse('final class Example {}', 'example.php');

        self::assertInstanceOf(ParseResult::class, $result);
        self::assertSame('example.php', $result->file->name);
        self::assertFalse($result->hasErrors);
        self::assertSame('T_OPEN_TAG', $result->tokens[0]->kind);
        self::assertSame('class', $result->declarations[0]->kind);
        self::assertSame('Example', $result->declarations[0]->name);
        self::assertSame('App\\Example', $result->declarations[0]->fqn);
    }

    #[Test]
    public function parse_file_uses_file_parser(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseFile: static fn(string $path): string => self::payloadJson($path),
            ),
        );

        $result = $code->parseFile('/tmp/example.php');

        self::assertSame('/tmp/example.php', $result->file->name);
    }

    #[Test]
    public function declarations_returns_declaration_index(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseFile: static fn(string $path): string => self::payloadJson($path),
            ),
        );

        $index = $code->declarationsForFile('/tmp/example.php');

        self::assertInstanceOf(DeclarationIndex::class, $index);
        self::assertSame('Example', $index->items[0]->name);
    }

    #[Test]
    public function tokens_returns_token_index(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseSource: static fn(string $source, ?string $name = null): string => self::payloadJson($name ?? 'inline.php'),
            ),
        );

        $index = $code->tokensForSource('<?php final class Example {}', 'example.php');

        self::assertInstanceOf(TokenIndex::class, $index);
        self::assertSame('T_OPEN_TAG', $index->items[0]->kind);
    }

    #[Test]
    public function context_backed_facade_resolves_parser_service(): void
    {
        $result = ParseResult::fromArray(self::payload());

        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('parseSource')
            ->with('final class Example {}', 'example.php')
            ->willReturn($result);

        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('service')
            ->with(CodeParser::class)
            ->willReturn($parser);

        $code = new ScopedCode($scope);

        self::assertSame($result, $code->parse('final class Example {}', 'example.php'));
    }

    #[Test]
    public function parser_error_payload_throws_runtime_exception(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseSource: static fn(): string => json_encode([
                    'ok' => false,
                    'message' => 'parser unavailable',
                ], JSON_THROW_ON_ERROR),
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('parser unavailable');

        $code->parse('broken');
    }

    #[Test]
    public function invalid_json_throws_payload_exception(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseSource: static fn(): string => '{',
            ),
        );

        $this->expectException(InvalidCodePayload::class);

        $code->parse('broken');
    }

    #[Test]
    public function missing_payload_shape_throws_payload_exception(): void
    {
        $code = new ScopedCode(
            parser: new NativeCodeParser(
                parseSource: static fn(): string => json_encode([
                    'ok' => true,
                    'has_errors' => false,
                    'errors' => [],
                    'tokens' => [],
                    'declarations' => [],
                ], JSON_THROW_ON_ERROR),
            ),
        );

        $this->expectException(InvalidCodePayload::class);

        $code->parse('broken');
    }

    private static function payloadJson(string $name): string
    {
        return json_encode(self::payload($name), JSON_THROW_ON_ERROR);
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
                'kind' => 'T_OPEN_TAG',
                'text' => '<?php',
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
