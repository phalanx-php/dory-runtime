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
use Phalanx\Task\Scopeable;

class CodeCheckCommand implements Scopeable, DescribesCommand
{
    public function __construct(
        private CodeParser $parser,
    ) {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $root = (string) $ctx->args->get('root', '.');
        $index = $this->parser->indexProject($root);
        $output = $ctx->service(StreamOutput::class);

        if ($ctx->options->flag('json')) {
            CodeCommandOutput::json($output, CodeCommandOutput::projectIndex($index));

            return $index->errors === [] ? 0 : 1;
        }

        $output->persist("Code: {$index->root}");
        $output->persist("Files: {$index->fileCount}");
        $output->persist("Declarations: {$index->declarationCount}");
        $output->persist("Tokens: {$index->tokenCount}");
        $output->persist("Nodes: {$index->nodeCount}");
        $output->persist("References: {$index->referenceCount}");
        $output->persist($index->errors === [] ? '[pass] no parse errors' : '[fail] parse errors');

        foreach ($index->errors as $error) {
            $output->persist(CodeCommandInput::errorLine($error));
        }

        return $index->errors === [] ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Parse and index PHP source files under a project root',
            arguments: [
                Arg::optional('root', 'Project root', '.'),
            ],
            options: [
                Opt::flag('json', '', 'Emit JSON output'),
            ],
            examples: [
                'dory code check',
                'dory code check app --json',
            ],
        );
    }
}
