<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\Arg;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Code\CodeParser;
use Phalanx\Dory\Code\DeclarationQuery;
use Phalanx\Dory\Code\DeclarationRecord;
use Phalanx\Task\Scopeable;

class CodeDeclarationsCommand implements Scopeable, DescribesCommand
{
    public function __construct(
        private CodeParser $parser,
    ) {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $root = (string) $ctx->args->get('root', '.');
        $query = new DeclarationQuery(
            kind: CodeCommandInput::nullableOption($ctx, 'kind'),
            name: CodeCommandInput::nullableOption($ctx, 'name'),
            fqn: CodeCommandInput::nullableOption($ctx, 'fqn'),
            file: CodeCommandInput::nullableOption($ctx, 'file'),
        );

        $result = $this->parser->queryDeclarations($root, $query);
        $output = $ctx->service(StreamOutput::class);

        if ($ctx->options->flag('json')) {
            CodeCommandOutput::json($output, CodeCommandOutput::declarationResult($result));

            return $result->errors === [] ? 0 : 1;
        }

        foreach ($result->declarations as $declaration) {
            $output->persist(self::declarationLine($declaration));
        }

        foreach ($result->errors as $error) {
            $output->persist(CodeCommandInput::errorLine($error));
        }

        return $result->errors === [] ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Search project declarations by kind, name, FQN, or file',
            arguments: [
                Arg::optional('root', 'Project root', '.'),
            ],
            options: [
                Opt::value('kind', '', 'Declaration kind'),
                Opt::value('name', '', 'Declaration name'),
                Opt::value('fqn', '', 'Declaration FQN'),
                Opt::value('file', '', 'Project-relative PHP file'),
                Opt::flag('json', '', 'Emit JSON output'),
            ],
            examples: [
                'dory code declarations --kind=class',
                'dory code declarations app --fqn=App\\Example::run',
            ],
        );
    }

    private static function declarationLine(DeclarationRecord $declaration): string
    {
        $file = $declaration->file ?? '-';
        $line = $declaration->nameSpan->startLine;

        return "{$declaration->kind} {$declaration->fqn} {$file}:{$line}";
    }
}
