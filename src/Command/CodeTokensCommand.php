<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Console\Console\Output\StreamOutput;
use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\TokenQuery;
use Phalanx\Bia\Code\TokenRecord;
use Phalanx\Task\Scopeable;

class CodeTokensCommand implements Scopeable, DescribesCommand
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
            && !CodeCommandInput::hasAnyOption($ctx, 'kind', 'text', 'file')
        ) {
            $message = 'Provide --kind, --text, --file, or --all before listing project tokens.';

            if ($ctx->options->flag('json')) {
                CodeCommandOutput::jsonError($output, $message);

                return 1;
            }

            $output->persist($message);

            return 1;
        }

        $query = new TokenQuery(
            kind: CodeCommandInput::nullableOption($ctx, 'kind'),
            text: CodeCommandInput::nullableOption($ctx, 'text'),
            file: CodeCommandInput::nullableOption($ctx, 'file'),
        );

        $result = $this->parser->queryTokens($root, $query);

        if ($ctx->options->flag('json')) {
            CodeCommandOutput::json($output, CodeCommandOutput::tokenResult($result));

            return $result->errors === [] ? 0 : 1;
        }

        foreach ($result->tokens as $token) {
            $output->persist(self::tokenLine($token));
        }

        foreach ($result->errors as $error) {
            $output->persist(CodeCommandInput::errorLine($error));
        }

        return $result->errors === [] ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Search project tokens by kind, text, or file',
            arguments: [
                Arg::optional('root', 'Project root', '.'),
            ],
            options: [
                Opt::value('kind', '', 'Token kind'),
                Opt::value('text', '', 'Token text'),
                Opt::value('file', '', 'Project-relative PHP file'),
                Opt::flag('all', '', 'List every project token'),
                Opt::flag('json', '', 'Emit JSON output'),
            ],
            examples: [
                'bia code tokens --text=class',
                'bia code tokens --all --json',
                'bia code tokens app --kind=Variable',
            ],
        );
    }

    private static function tokenLine(TokenRecord $token): string
    {
        $file = $token->file ?? '-';
        $line = $token->span->startLine;
        $text = json_encode($token->text, JSON_THROW_ON_ERROR);

        return "{$token->kind} {$text} {$file}:{$line}";
    }
}
