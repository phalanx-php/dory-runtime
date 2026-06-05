<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Scoped;

use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\CodeProjectIndex;
use Phalanx\Bia\Code\DeclarationIndex;
use Phalanx\Bia\Code\DeclarationQuery;
use Phalanx\Bia\Code\DeclarationQueryResult;
use Phalanx\Bia\Code\NodeQuery;
use Phalanx\Bia\Code\NodeQueryResult;
use Phalanx\Bia\Code\ParseResult;
use Phalanx\Bia\Code\ReferenceQuery;
use Phalanx\Bia\Code\ReferenceQueryResult;
use Phalanx\Bia\Code\SpanRecord;
use Phalanx\Bia\Code\TokenIndex;
use Phalanx\Bia\Code\TokenQuery;
use Phalanx\Bia\Code\TokenQueryResult;
use Phalanx\Bia\Scoped\ScopedCode;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScopedCodeTest extends TestCase
{
    #[Test]
    public function parse_uses_injected_parser(): void
    {
        $result = self::parseResult('example.php');
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('parseSource')
            ->with('final class Example {}', 'example.php')
            ->willReturn($result);

        $code = new ScopedCode(parser: $parser);

        self::assertSame($result, $code->parse('final class Example {}', 'example.php'));
    }

    #[Test]
    public function parse_file_uses_injected_parser(): void
    {
        $path = sys_get_temp_dir() . '/bia-runtime-example.php';
        $result = self::parseResult($path);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('parseFile')
            ->with($path)
            ->willReturn($result);

        $code = new ScopedCode(parser: $parser);

        self::assertSame($result, $code->parseFile($path));
    }

    #[Test]
    public function declarations_for_file_returns_declaration_index(): void
    {
        $path = sys_get_temp_dir() . '/bia-runtime-example.php';
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())->method('parseFile')->with($path)->willReturn(self::parseResult($path));

        $index = (new ScopedCode(parser: $parser))->declarationsForFile($path);

        self::assertInstanceOf(DeclarationIndex::class, $index);
        self::assertSame('Example', $index->items[0]->name);
    }

    #[Test]
    public function tokens_for_source_returns_token_index(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())->method('parseSource')
            ->with('<?php final class Example {}', 'example.php')
            ->willReturn(self::parseResult('example.php'));

        $index = (new ScopedCode(parser: $parser))->tokensForSource('<?php final class Example {}', 'example.php');

        self::assertInstanceOf(TokenIndex::class, $index);
        self::assertSame('Class', $index->items[0]->kind);
    }

    #[Test]
    public function project_methods_default_to_current_working_directory(): void
    {
        $cwd = getcwd() ?: '.';
        $project = new CodeProjectIndex($cwd, [], 0, 0, 0, 0, 0, []);
        $declarations = new DeclarationQueryResult($cwd, [], []);
        $tokens = new TokenQueryResult($cwd, [], []);
        $nodes = new NodeQueryResult($cwd, [], []);
        $references = new ReferenceQueryResult($cwd, [], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())->method('indexProject')->with($cwd)->willReturn($project);
        $parser->expects(self::once())->method('queryDeclarations')->with($cwd, null)->willReturn($declarations);
        $parser->expects(self::once())->method('queryTokens')->with($cwd, null)->willReturn($tokens);
        $parser->expects(self::once())->method('queryNodes')->with($cwd, null)->willReturn($nodes);
        $parser->expects(self::once())->method('queryReferences')->with($cwd, null)->willReturn($references);

        $code = new ScopedCode(parser: $parser);

        self::assertSame($project, $code->indexProject());
        self::assertSame($declarations, $code->declarations());
        self::assertSame($tokens, $code->tokens());
        self::assertSame($nodes, $code->nodes());
        self::assertSame($references, $code->references());
    }

    #[Test]
    public function project_methods_pass_explicit_queries_to_parser(): void
    {
        $root = sys_get_temp_dir() . '/bia-runtime-project';
        $declarationQuery = new DeclarationQuery(kind: 'class');
        $tokenQuery = new TokenQuery(text: 'class');
        $nodeQuery = new NodeQuery(kind: 'method');
        $referenceQuery = new ReferenceQuery(kind: 'function');
        $declarations = new DeclarationQueryResult($root, [], []);
        $tokens = new TokenQueryResult($root, [], []);
        $nodes = new NodeQueryResult($root, [], []);
        $references = new ReferenceQueryResult($root, [], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryDeclarations')
            ->with($root, $declarationQuery)
            ->willReturn($declarations);
        $parser->expects(self::once())
            ->method('queryTokens')
            ->with($root, $tokenQuery)
            ->willReturn($tokens);
        $parser->expects(self::once())
            ->method('queryNodes')
            ->with($root, $nodeQuery)
            ->willReturn($nodes);
        $parser->expects(self::once())
            ->method('queryReferences')
            ->with($root, $referenceQuery)
            ->willReturn($references);

        $code = new ScopedCode(parser: $parser);

        self::assertSame($declarations, $code->declarations($root, $declarationQuery));
        self::assertSame($tokens, $code->tokens($root, $tokenQuery));
        self::assertSame($nodes, $code->nodes($root, $nodeQuery));
        self::assertSame($references, $code->references($root, $referenceQuery));
    }

    #[Test]
    public function context_backed_facade_resolves_parser_service(): void
    {
        $result = self::parseResult('example.php');

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

    private static function parseResult(string $name): ParseResult
    {
        $span = new SpanRecord(
            0,
            5,
            1,
            1,
            1,
            6,
        );

        return ParseResult::fromArray([
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
                'span' => [
                    'start_offset' => $span->startOffset,
                    'end_offset' => $span->endOffset,
                    'start_line' => $span->startLine,
                    'start_column' => $span->startColumn,
                    'end_line' => $span->endLine,
                    'end_column' => $span->endColumn,
                ],
            ]],
            'declarations' => [[
                'kind' => 'class',
                'name' => 'Example',
                'namespace' => 'App',
                'declaring_type' => null,
                'fqn' => 'App\\Example',
                'span' => [
                    'start_offset' => $span->startOffset,
                    'end_offset' => $span->endOffset,
                    'start_line' => $span->startLine,
                    'start_column' => $span->startColumn,
                    'end_line' => $span->endLine,
                    'end_column' => $span->endColumn,
                ],
                'name_span' => [
                    'start_offset' => $span->startOffset,
                    'end_offset' => $span->endOffset,
                    'start_line' => $span->startLine,
                    'start_column' => $span->startColumn,
                    'end_line' => $span->endLine,
                    'end_column' => $span->endColumn,
                ],
            ]],
            'nodes' => [[
                'kind' => 'method',
                'name' => 'run',
                'span' => [
                    'start_offset' => $span->startOffset,
                    'end_offset' => $span->endOffset,
                    'start_line' => $span->startLine,
                    'start_column' => $span->startColumn,
                    'end_line' => $span->endLine,
                    'end_column' => $span->endColumn,
                ],
                'context' => 'App\\Example',
            ]],
            'references' => [[
                'kind' => 'function',
                'name' => 'helper',
                'receiver' => null,
                'span' => [
                    'start_offset' => $span->startOffset,
                    'end_offset' => $span->endOffset,
                    'start_line' => $span->startLine,
                    'start_column' => $span->startColumn,
                    'end_line' => $span->endLine,
                    'end_column' => $span->endColumn,
                ],
                'context' => 'App\\Example::run',
            ]],
        ]);
    }
}
