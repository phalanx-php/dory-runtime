<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Output\StreamOutput;
use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\ReferenceQuery;
use Phalanx\Bia\Code\ReferenceRecord;
use Phalanx\Task\Scopeable;

class CodeReferencesCommand implements Scopeable, DescribesCommand
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
                'receiver',
                'file',
                'context',
            )
        ) {
            $message = 'Provide --kind, --name, --receiver, --file, --context, or --all before listing project references.';

            if ($ctx->options->flag('json')) {
                CodeCommandOutput::jsonError($output, $message);

                return 1;
            }

            $output->persist($message);

            return 1;
        }

        $query = new ReferenceQuery(
            kind: CodeCommandInput::nullableOption($ctx, 'kind'),
            name: CodeCommandInput::nullableOption($ctx, 'name'),
            receiver: CodeCommandInput::nullableOption($ctx, 'receiver'),
            file: CodeCommandInput::nullableOption($ctx, 'file'),
            context: CodeCommandInput::nullableOption($ctx, 'context'),
        );

        $result = $this->parser->queryReferences($root, $query);

        if ($ctx->options->flag('json')) {
            CodeCommandOutput::json($output, CodeCommandOutput::referenceResult($result));

            return $result->errors === [] ? 0 : 1;
        }

        foreach ($result->references as $reference) {
            $output->persist(self::referenceLine($reference));
        }

        foreach ($result->errors as $error) {
            $output->persist(CodeCommandInput::errorLine($error));
        }

        return $result->errors === [] ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Search project code references by kind, name, receiver, file, or context',
            arguments: [
                Arg::optional('root', 'Project root', '.'),
            ],
            options: [
                Opt::value('kind', '', 'Reference kind'),
                Opt::value('name', '', 'Reference name'),
                Opt::value('receiver', '', 'Reference receiver or qualifier'),
                Opt::value('file', '', 'Project-relative PHP file'),
                Opt::value('context', '', 'Enclosing declaration FQN'),
                Opt::flag('all', '', 'List every project reference'),
                Opt::flag('json', '', 'Emit JSON output'),
            ],
            examples: [
                'bia code references --kind=function',
                'bia code references app --kind=method --name=handle --receiver=\'$this\' --json',
            ],
        );
    }

    private static function referenceLine(ReferenceRecord $reference): string
    {
        $file = $reference->file ?? '-';
        $line = $reference->span->startLine;
        $context = $reference->context ?? '-';
        $receiver = $reference->receiver ?? '-';

        return "{$reference->kind} {$reference->name} {$receiver} {$context} {$file}:{$line}";
    }
}
