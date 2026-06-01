<?php

declare(strict_types=1);

namespace Phalanx\Dory\Scoped;

use Phalanx\Dory\Code\CodeParser;
use Phalanx\Dory\Code\CodeProjectIndex;
use Phalanx\Dory\Code\DeclarationIndex;
use Phalanx\Dory\Code\DeclarationQuery;
use Phalanx\Dory\Code\DeclarationQueryResult;
use Phalanx\Dory\Code\NativeCodeParser;
use Phalanx\Dory\Code\ParseResult;
use Phalanx\Dory\Code\TokenIndex;
use Phalanx\Dory\Code\TokenQuery;
use Phalanx\Dory\Code\TokenQueryResult;
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
