<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Command;

use Phalanx\Console\Command\CommandArgs;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\CommandOptions;
use Phalanx\Console\Command\ExecutionContext;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Console\Output\TerminalEnvironment;
use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\CodeNodeRecord;
use Phalanx\Bia\Code\CodeProjectIndex;
use Phalanx\Bia\Code\DeclarationQuery;
use Phalanx\Bia\Code\DeclarationQueryResult;
use Phalanx\Bia\Code\DeclarationRecord;
use Phalanx\Bia\Code\NodeQuery;
use Phalanx\Bia\Code\NodeQueryResult;
use Phalanx\Bia\Code\ParseError;
use Phalanx\Bia\Code\ReferenceQuery;
use Phalanx\Bia\Code\ReferenceQueryResult;
use Phalanx\Bia\Code\ReferenceRecord;
use Phalanx\Bia\Code\SourceFileRecord;
use Phalanx\Bia\Code\SpanRecord;
use Phalanx\Bia\Code\TokenQuery;
use Phalanx\Bia\Code\TokenQueryResult;
use Phalanx\Bia\Code\TokenRecord;
use Phalanx\Bia\Command\CodeCheckCommand;
use Phalanx\Bia\Command\CodeDeclarationsCommand;
use Phalanx\Bia\Command\CodeNodesCommand;
use Phalanx\Bia\Command\CodeReferencesCommand;
use Phalanx\Bia\Command\CodeTokensCommand;
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
        self::assertStringContainsString('Nodes: 2', $output);
        self::assertStringContainsString('References: 1', $output);
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
    public function tokens_command_hydrates_real_argv_options(): void
    {
        $result = new TokenQueryResult('app', [self::token()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryTokens')
            ->with(self::equalTo('app'), self::callback(
                static fn (TokenQuery $query): bool => $query->kind === 'Variable'
                    && $query->text === null
                    && $query->file === 'src/Example.php',
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->argvContext(
            CodeTokensCommand::commandConfig(),
            ['app', '--kind=Variable', '--file=src/Example.php'],
        );
        $exit = (new CodeTokensCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(0, $exit);
        self::assertStringContainsString('Class "class" src/Example.php:3', $output);
    }

    #[Test]
    public function nodes_command_passes_filters_and_can_emit_json(): void
    {
        $result = new NodeQueryResult('app', [self::node()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryNodes')
            ->with(self::equalTo('app'), self::callback(
                static fn (NodeQuery $query): bool => $query->kind === 'method'
                    && $query->name === 'run'
                    && $query->file === 'src/Example.php'
                    && $query->context === 'App\\Example',
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->context(
            ['root' => 'app'],
            ['kind' => 'method', 'name' => 'run', 'file' => 'src/Example.php', 'context' => 'App\\Example', 'json' => true],
        );
        $exit = (new CodeNodesCommand($parser))($ctx);

        rewind($stream);
        $payload = json_decode((string) stream_get_contents($stream), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(0, $exit);
        self::assertSame('method', $payload['nodes'][0]['kind']);
        self::assertSame('run', $payload['nodes'][0]['name']);
    }

    #[Test]
    public function references_command_passes_filters_and_prints_matches(): void
    {
        $result = new ReferenceQueryResult('app', [self::reference()], []);
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::once())
            ->method('queryReferences')
            ->with(self::equalTo('app'), self::callback(
                static fn (ReferenceQuery $query): bool => $query->kind === 'method'
                    && $query->name === 'name'
                    && $query->receiver === '$this'
                    && $query->file === null
                    && $query->context === null,
            ))
            ->willReturn($result);

        [$ctx, $stream] = $this->context(
            ['root' => 'app'],
            ['kind' => 'method', 'name' => 'name', 'receiver' => '$this'],
        );
        $exit = (new CodeReferencesCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(0, $exit);
        self::assertStringContainsString(
            'method name $this App\\Example::run src/Example.php:3',
            $output,
        );
    }

    #[Test]
    public function nodes_command_requires_filter_or_all_flag(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::never())->method('queryNodes');

        [$ctx, $stream] = $this->context(['root' => 'app']);
        $exit = (new CodeNodesCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Provide --kind, --name, --file, --context, or --all', $output);
    }

    #[Test]
    public function references_command_requires_filter_or_all_flag(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::never())->method('queryReferences');

        [$ctx, $stream] = $this->context(['root' => 'app']);
        $exit = (new CodeReferencesCommand($parser))($ctx);

        rewind($stream);
        $output = stream_get_contents($stream);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Provide --kind, --name, --receiver, --file, --context, or --all', $output);
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
    public function tokens_command_json_validation_failure_is_machine_readable(): void
    {
        $parser = $this->createMock(CodeParser::class);
        $parser->expects(self::never())->method('queryTokens');

        [$ctx, $stream] = $this->context(['root' => 'app'], ['json' => true]);
        $exit = (new CodeTokensCommand($parser))($ctx);

        rewind($stream);
        $payload = json_decode((string) stream_get_contents($stream), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $exit);
        self::assertFalse($payload['ok']);
        self::assertSame(
            'Provide --kind, --text, --file, or --all before listing project tokens.',
            $payload['message'],
        );
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

    /**
     * @param list<string> $rawArgs
     * @return array{CommandContext, resource}
     */
    private function argvContext(CommandConfig $config, array $rawArgs): array
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
            ExecutionContext::fromInput(
                $inner,
                'tokens',
                $config,
                $rawArgs,
                'test-code-command-argv',
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
            nodeCount: 2,
            referenceCount: 1,
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
        return new TokenRecord(
            'Class',
            'class',
            self::span(line: 3),
            'src/Example.php',
        );
    }

    private static function node(): CodeNodeRecord
    {
        return new CodeNodeRecord(
            'method',
            'run',
            self::span(line: 3),
            'App\\Example',
            'src/Example.php',
        );
    }

    private static function reference(): ReferenceRecord
    {
        return new ReferenceRecord(
            'method',
            'name',
            '$this',
            self::span(line: 3),
            'App\\Example::run',
            'src/Example.php',
        );
    }

    private static function span(int $line = 1): SpanRecord
    {
        return new SpanRecord(
            0,
            5,
            $line,
            1,
            $line,
            6,
        );
    }
}
