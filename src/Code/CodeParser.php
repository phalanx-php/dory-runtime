<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

interface CodeParser
{
    public function parseSource(string $source, ?string $name = null): ParseResult;

    public function parseFile(string $path): ParseResult;

    public function indexProject(string $root): CodeProjectIndex;

    public function queryDeclarations(string $root, ?DeclarationQuery $query = null): DeclarationQueryResult;

    public function queryTokens(string $root, ?TokenQuery $query = null): TokenQueryResult;
}
