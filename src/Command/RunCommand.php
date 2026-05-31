<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\Arg;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Dory\Runtime\DoryConfig;
use Phalanx\Dory\Runtime\DoryScriptExecutor;
use Phalanx\Task\Scopeable;

class RunCommand implements Scopeable, DescribesCommand
{
    public function __construct(private DoryScriptExecutor $scripts)
    {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $input = (string) $ctx->args->required('script');
        $scriptPath = self::resolveScript($input, $ctx);

        return $this->scripts->execute($ctx, $scriptPath, self::configFor($ctx));
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Run a Dory script or inline PHP code',
            arguments: [
                Arg::required('script', 'Script path or inline PHP code'),
            ],
            options: [
                Opt::flag('verbose', 'v', 'Show detailed output'),
                Opt::value('timeout', 't', 'Script timeout in seconds', '30'),
            ],
            examples: [
                'dory run deploy.php',
                "dory run '1 + 1'",
                'dory run sync.php --timeout=120',
                'dory r migrate.php -v',
            ],
            aliases: ['r'],
        );
    }

    private static function resolveScript(string $input, CommandContext $ctx): string
    {
        $resolved = realpath($input);

        if ($resolved !== false && file_exists($resolved)) {
            return $resolved;
        }

        if (self::looksLikePath($input)) {
            throw new \RuntimeException("Script not found: {$input}");
        }

        return self::writeInlineScript($input, $ctx);
    }

    private static function looksLikePath(string $input): bool
    {
        return str_contains($input, '/') || str_contains($input, '\\') || str_ends_with($input, '.php');
    }

    private static function writeInlineScript(string $code, CommandContext $ctx): string
    {
        $code = trim($code);
        $isExpression = !str_contains($code, ';') && !str_contains($code, '{');

        if ($isExpression) {
            $body = "\$__r = ({$code});\nif (\$__r !== null) { dory()->dump(\$__r); }\nreturn 0;";
        } else {
            $body = str_ends_with($code, ';') || str_ends_with($code, '}') || str_ends_with($code, '?>')
                ? $code
                : "{$code};";
            $body .= "\nreturn 0;";
        }

        $php = "<?php declare(strict_types=1);\n{$body}\n";
        $tmp = tempnam(sys_get_temp_dir(), 'dory_');
        if ($tmp === false) {
            throw new \RuntimeException('Failed to create inline script file.');
        }

        $path = "{$tmp}.php";
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to prepare inline script file.');
        }

        file_put_contents($path, $php);
        $ctx->onDispose(static fn() => @unlink($path));

        return $path;
    }

    private static function configFor(CommandContext $ctx): DoryConfig
    {
        $base = $ctx->service(DoryConfig::class);
        $timeout = $ctx->options->get('timeout');

        return new DoryConfig(
            scriptTimeout: $timeout === null ? $base->scriptTimeout : (float) $timeout,
            maxConcurrency: $base->maxConcurrency,
            verbose: $ctx->options->flag('verbose') || $base->verbose,
            embedded: $base->embedded,
        );
    }
}
