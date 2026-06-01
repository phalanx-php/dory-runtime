<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Command;

use Phalanx\Archon\Command\CommandArgs;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\CommandOptions;
use Phalanx\Archon\Command\ExecutionContext;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Archon\Console\Output\TerminalEnvironment;
use Phalanx\Dory\Code\CodeParser;
use Phalanx\Dory\Code\CodeProjectIndex;
use Phalanx\Dory\Code\DeclarationQuery;
use Phalanx\Dory\Code\DeclarationQueryResult;
use Phalanx\Dory\Code\DeclarationRecord;
use Phalanx\Dory\Code\ParseError;
use Phalanx\Dory\Code\SourceFileRecord;
use Phalanx\Dory\Code\SpanRecord;
use Phalanx\Dory\Code\TokenQuery;
use Phalanx\Dory\Code\TokenQueryResult;
use Phalanx\Dory\Code\TokenRecord;
use Phalanx\Dory\Command\CodeCheckCommand;
use Phalanx\Dory\Command\CodeDeclarationsCommand;
use Phalanx\Dory\Command\CodeTokensCommand;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodeCommandTest extends TestCase
{
    #[Test]
    public function check_reports_project_summary(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('indexProject')
            ->with('app')
            ->willReturn(self::projectIndex('app'));

        [$ctx, $stream] = $this->context(['root' => 'app']);
        $exit = (new CodeCheckCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Code: app', $output);
        self::assertStringContainsString('Files: 1', $output);
        self::assertStringContainsString('[pass] no parse errors', $output);
    }

    #[Test]
    public function check_json_returns_failure_when_parse_errors_exist(): void
    {
        $parser = $this->createStub(CodeParser::class);
        $parser->method('indexProject')->willReturn(self::projectIndex('app', [
            new ParseError('src/Bad.php: unexpected token', self::span()),
        ]));

        [$ctx, $stream] = $this->context(['root' => 'app'], ['json' => true]);
        $exit = (new CodeCheckCommand($parser))($ctx);

        rewind($stream);
        $payload = json_decode((string) stream_get_contents($stream), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exit);
        self::assertSame('app', $payload['root']);
        self::assertSame('src/Bad.php: unexpected token', $payload['errors'][0]['message']);
    }

    #[Test]
    public function declarations_command_passes_filters_and_prints_matches(): void
    {
        $result = new DeclarationQueryResult('app', [self::declaration()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryDeclarations')
            ->with(self::equalTo('app'), self::callback(
                static fn (DeclarationQuery $query): bool => $query->kind === 'class'
                    && $query->name === 'Example'
                    && $query->fqn === null
                    && $query->file === null,
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->context(['root' => 'app'], ['kind' => 'class', 'name' => 'Example']);
        $exit = (new CodeDeclarationsCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(0, $exit);
        self::assertStringContainsString('class App\\Example src/Example.php:3', $output);
    }

    #[Test]
    public function tokens_command_passes_filters_and_can_emit_json(): void
    {
        $result = new TokenQueryResult('app', [self::token()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryTokens')
            ->with(self::equalTo('app'), self::callback(
                static fn (TokenQuery $query): bool => $query->kind === null
                    && $query->text === 'class'
                    && $query->file === 'src/Example.php',
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->context(
            ['root' => 'app'],
            ['text' => 'class', 'file' => 'src/Example.php', 'json' => true],
        );
        $exit = (new CodeTokensCommand($parser))($ctx);

        rewind($stream);
        $payload = json_decode((string) stream_get_contents($stream), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exit);
        self::assertSame('Class', $payload['tokens'][0]['kind']);
        self::assertSame('class', $payload['tokens'][0]['text']);
    }

    #[Test]
    public function tokens_command_requires_filter_or_all_flag(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::never())->method('queryTokens');

        [$ctx, $stream] = $this->context(['root' => 'app']);
        $exit = (new CodeTokensCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Provide --kind, --text, --file, or --all', $output);
    }

    #[Test]
    public function tokens_command_all_flag_allows_unfiltered_queries(): void
    {
        $result = new TokenQueryResult('app', [self::token()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryTokens')
            ->with(self::equalTo('app'), self::callback(
                static fn (TokenQuery $query): bool => $query->kind === null
                    && $query->text === null
                    && $query->file === null,
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->context(['root' => 'app'], ['all' => true]);
        $exit = (new CodeTokensCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Class "class" src/Example.php:3', $output);
    }

    /**
     * @param array<string, mixed> $args
     * @param array<string, mixed> $options
     * @return array{CommandContext, resource}
     */
    private function context(array $args = [], array $options = []): array
    {
        $stream = fopen('php://memory', 'rw');
        self::assertIsResource($stream);

        $output = new StreamOutput($stream, new TerminalEnvironment(isTty: false));
        $inner = $this->createStub(ExecutionScope::class);
        $inner->method('service')->willReturnCallback(
            static fn (string $type) => match ($type) {
                StreamOutput::class => $output,
                default => throw new \RuntimeException('Unexpected service: ' . $type),
            },
        );

        return [
            new ExecutionContext(
                $inner,
                'code',
                new CommandArgs($args),
                new CommandOptions($options),
                new CommandConfig(),
                'test-code-command',
            ),
            $stream,
        ];
    }

    /** @param list<ParseError> $errors */
    private static function projectIndex(string $root, array $errors = []): CodeProjectIndex
    {
        return new CodeProjectIndex(
            root: $root,
            files: [new SourceFileRecord('1', 'src/Example.php', false)],
            fileCount: 1,
            declarationCount: 1,
            tokenCount: 1,
            errors: $errors,
        );
    }

    private static function declaration(): DeclarationRecord
    {
        return new DeclarationRecord(
            kind: 'class',
            name: 'Example',
            namespace: 'App',
            declaringType: null,
            fqn: 'App\\Example',
            span: self::span(),
            nameSpan: self::span(line: 3),
            file: 'src/Example.php',
        );
    }

    private static function token(): TokenRecord
    {
        return new TokenRecord('Class', 'class', self::span(line: 3), 'src/Example.php');
    }

    private static function span(int $line = 1): SpanRecord
    {
        return new SpanRecord(0, 5, $line, 1, $line, 6);
    }
}
