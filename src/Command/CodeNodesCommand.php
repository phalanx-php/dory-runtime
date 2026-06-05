<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Console\Output\StreamOutput;
use Phalanx\Bia\Code\CodeNodeRecord;
use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\NodeQuery;
use Phalanx\Task\Scopeable;

class CodeNodesCommand implements Scopeable, DescribesCommand
{
    public function __construct(
        private CodeParser $parser,
    ) {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $root = (string) $ctx->args->get('root', '.');
        $output = $ctx->service(StreamOutput::class);

        if (
            !$ctx->options->flag('all')
            && !CodeCommandInput::hasAnyOption(
                $ctx,
                'kind',
                'name',
                'file',
                'context',
            )
        ) {
            $message = 'Provide --kind, --name, --file, --context, or --all before listing project nodes.';

            if ($ctx->options->flag('json')) {
                CodeCommandOutput::jsonError($output, $message);

                return 1;
            }

            $output->persist($message);

            return 1;
        }

        $query = new NodeQuery(
            kind: CodeCommandInput::nullableOption($ctx, 'kind'),
            name: CodeCommandInput::nullableOption($ctx, 'name'),
            file: CodeCommandInput::nullableOption($ctx, 'file'),
            context: CodeCommandInput::nullableOption($ctx, 'context'),
        );

        $result = $this->parser->queryNodes($root, $query);

        if ($ctx->options->flag('json')) {
            CodeCommandOutput::json($output, CodeCommandOutput::nodeResult($result));

            return $result->errors === [] ? 0 : 1;
        }

        foreach ($result->nodes as $node) {
            $output->persist(self::nodeLine($node));
        }

        foreach ($result->errors as $error) {
            $output->persist(CodeCommandInput::errorLine($error));
        }

        return $result->errors === [] ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Search project AST projection nodes by kind, name, file, or context',
            arguments: [
                Arg::optional('root', 'Project root', '.'),
            ],
            options: [
                Opt::value('kind', '', 'Node kind'),
                Opt::value('name', '', 'Node name'),
                Opt::value('file', '', 'Project-relative PHP file'),
                Opt::value('context', '', 'Enclosing declaration FQN'),
                Opt::flag('all', '', 'List every project node'),
                Opt::flag('json', '', 'Emit JSON output'),
            ],
            examples: [
                'bia code nodes --kind=method',
                'bia code nodes app --name=run --json',
            ],
        );
    }

    private static function nodeLine(CodeNodeRecord $node): string
    {
        $file = $node->file ?? '-';
        $line = $node->span->startLine;
        $name = $node->name ?? '-';
        $context = $node->context ?? '-';

        return "{$node->kind} {$name} {$context} {$file}:{$line}";
    }
}
