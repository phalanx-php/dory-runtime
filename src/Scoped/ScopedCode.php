<?php

declare(strict_types=1);

namespace Phalanx\Bia\Scoped;

use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\CodeProjectIndex;
use Phalanx\Bia\Code\DeclarationIndex;
use Phalanx\Bia\Code\DeclarationQuery;
use Phalanx\Bia\Code\DeclarationQueryResult;
use Phalanx\Bia\Code\NativeCodeParser;
use Phalanx\Bia\Code\NodeQuery;
use Phalanx\Bia\Code\NodeQueryResult;
use Phalanx\Bia\Code\ParseResult;
use Phalanx\Bia\Code\ReferenceQuery;
use Phalanx\Bia\Code\ReferenceQueryResult;
use Phalanx\Bia\Code\TokenIndex;
use Phalanx\Bia\Code\TokenQuery;
use Phalanx\Bia\Code\TokenQueryResult;
use Phalanx\Scope\ExecutionScope;

class ScopedCode
{
    public function __construct(
        private ?ExecutionScope $ctx = null,
        private ?CodeParser $parser = null,
    ) {
    }

    public function parse(string $source, ?string $name = null): ParseResult
    {
        return $this->parser()->parseSource($source, $name);
    }

    public function parseFile(string $path): ParseResult
    {
        return $this->parser()->parseFile($path);
    }

    public function declarationsForFile(string $path): DeclarationIndex
    {
        return DeclarationIndex::fromParseResult($this->parseFile($path));
    }

    public function tokensForSource(string $source, ?string $name = null): TokenIndex
    {
        return TokenIndex::fromParseResult($this->parse($source, $name));
    }

    public function indexProject(?string $root = null): CodeProjectIndex
    {
        return $this->parser()->indexProject($root ?? $this->workingDirectory());
    }

    public function declarations(?string $root = null, ?DeclarationQuery $query = null): DeclarationQueryResult
    {
        return $this->parser()->queryDeclarations($root ?? $this->workingDirectory(), $query);
    }

    public function tokens(?string $root = null, ?TokenQuery $query = null): TokenQueryResult
    {
        return $this->parser()->queryTokens($root ?? $this->workingDirectory(), $query);
    }

    public function nodes(?string $root = null, ?NodeQuery $query = null): NodeQueryResult
    {
        return $this->parser()->queryNodes($root ?? $this->workingDirectory(), $query);
    }

    public function references(?string $root = null, ?ReferenceQuery $query = null): ReferenceQueryResult
    {
        return $this->parser()->queryReferences($root ?? $this->workingDirectory(), $query);
    }

    private function parser(): CodeParser
    {
        if ($this->parser !== null) {
            return $this->parser;
        }

        if ($this->ctx === null) {
            return $this->parser = new NativeCodeParser();
        }

        return $this->parser = $this->ctx->service(CodeParser::class);
    }

    private function workingDirectory(): string
    {
        return getcwd() ?: '.';
    }
}
