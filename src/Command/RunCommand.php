<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Bia\Exception\ScriptFault;
use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaScriptExecutor;
use Phalanx\Task\Scopeable;
use Throwable;

class RunCommand implements Scopeable, DescribesCommand
{
    public function __construct(
        private BiaScriptExecutor $scripts,
    ) {
    }

    public function __invoke(CommandContext $ctx): int
    {
        $input = (string) $ctx->args->required('script');
        $scriptPath = self::resolveScript($input, $ctx);
        $isInline = !self::looksLikePath($input)
            && (realpath($input) === false || !file_exists($input));

        try {
            return $this->scripts->execute($ctx, $scriptPath, self::configFor($ctx));
        } catch (Throwable $e) {
            throw $isInline
                ? ScriptFault::inline($e)
                : ScriptFault::file($e, $scriptPath);
        }
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Run a Bia script or inline PHP code',
            arguments: [
                Arg::required('script', 'Script path or inline PHP code'),
            ],
            options: [
                Opt::flag('verbose', 'v', 'Show detailed output'),
                Opt::value('timeout', 't', 'Script timeout in seconds', '30'),
            ],
            examples: [
                'bia run deploy.php',
                "bia run '1 + 1'",
                'bia run sync.php --timeout=120',
                'bia r migrate.php -v',
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
            $body = "\$__r = ({$code});\nif (\$__r !== null) { bia()->dump(\$__r); }\nreturn 0;";
        } else {
            $body = str_ends_with($code, ';') || str_ends_with($code, '}') || str_ends_with($code, '?>')
                ? $code
                : "{$code};";
            $body .= "\nreturn 0;";
        }

        $php = "<?php declare(strict_types=1);\n{$body}\n";
        $tmp = tempnam(sys_get_temp_dir(), 'bia_');
        if ($tmp === false) {
            throw new \RuntimeException('Failed to create inline script file.');
        }

        $path = "{$tmp}.php";
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Failed to prepare inline script file.');
        }

        file_put_contents($path, $php);
        $ctx->onDispose(static function () use ($path): void {
            @unlink($path);
        });

        return $path;
    }

    private static function configFor(CommandContext $ctx): BiaConfig
    {
        $base = $ctx->service(BiaConfig::class);
        $timeout = $ctx->options->get('timeout');

        return new BiaConfig(
            scriptTimeout: $timeout === null ? $base->scriptTimeout : (float) $timeout,
            maxConcurrency: $base->maxConcurrency,
            verbose: $ctx->options->flag('verbose') || $base->verbose,
            embedded: $base->embedded,
        );
    }
}
