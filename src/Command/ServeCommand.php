<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\Arg;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Boot\AppContext;
use Phalanx\Dory\Runtime\DoryConfig;
use Phalanx\Dory\Runtime\DoryExecutionContext;
use Phalanx\Dory\Runtime\DoryProjectConfig;
use Phalanx\Dory\Runtime\DoryScriptRoute;
use Phalanx\Dory\Runtime\DoryServeConfig;
use Phalanx\Dory\Runtime\DoryServeServiceBundle;
use Phalanx\Dory\Runtime\DoryServiceBundle;
use Phalanx\Dory\Runtime\ScriptRunner;
use Phalanx\Grammata\FilesystemServiceBundle;
use Phalanx\Iris\HttpServiceBundle;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Stoa\RouteGroup;
use Phalanx\Stoa\Stoa;
use Phalanx\Stoa\StoaApplication;
use Phalanx\Stoa\StoaApplicationBuilder;
use Phalanx\Stoa\StoaServerConfig;
use Phalanx\Task\Scopeable;
use RuntimeException;

class ServeCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $scriptPath = self::resolveScript((string) $ctx->args->required('script'));
        $base = $ctx->service(DoryConfig::class);
        $serverConfig = self::serverConfig($ctx);

        $app = self::isStoaMode($ctx, $scriptPath)
            ? self::loadStoaApplication($ctx, $scriptPath, $base, $serverConfig)
            : self::scriptApplication($ctx, $scriptPath, $base, $serverConfig);

        return $app->run();
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Serve a Dory or Stoa script over HTTP',
            arguments: [
                Arg::required('script', 'Path to the Dory or Stoa script'),
            ],
            options: [
                Opt::value('host', '', 'Listen host'),
                Opt::value('port', 'p', 'Listen port'),
                Opt::value('listen', 'l', 'Listen address as host:port'),
                Opt::flag('stoa', '', 'Treat the script as a Stoa application bootstrap'),
            ],
            examples: [
                'dory serve app.php',
                'APP_PORT=9000 dory serve app.php',
                'dory serve app.php --listen=127.0.0.1:9000',
                'dory serve app.php --stoa',
            ],
        );
    }

    private static function resolveScript(string $scriptPath): string
    {
        $resolved = realpath($scriptPath);

        if ($resolved === false) {
            throw new RuntimeException("Script not found: {$scriptPath}");
        }

        return $resolved;
    }

    private static function loadStoaApplication(
        CommandContext $ctx,
        string $scriptPath,
        DoryConfig $doryConfig,
        StoaServerConfig $serverConfig,
    ): StoaApplication {
        $result = null;

        ob_start();
        try {
            $result = $ctx->execute(static function (ExecutionScope $scope) use ($scriptPath, $doryConfig): mixed {
                return ScriptRunner::execute(new DoryExecutionContext(
                    inner: $scope,
                    scriptPath: $scriptPath,
                    config: $doryConfig,
                ));
            });
        } finally {
            ob_end_clean();
        }

        if ($result instanceof StoaApplication) {
            return $result;
        }

        if ($result instanceof StoaApplicationBuilder) {
            return $result->withServerConfig($serverConfig)->build();
        }

        throw new RuntimeException('Stoa serve mode requires the script to return a StoaApplication or StoaApplicationBuilder.');
    }

    private static function scriptApplication(
        CommandContext $ctx,
        string $scriptPath,
        DoryConfig $doryConfig,
        StoaServerConfig $serverConfig,
    ): StoaApplication {
        $context = $ctx->service(AppContext::class);

        return Stoa::starting($context->values)
            ->providers(
                new DoryServiceBundle(),
                new HttpServiceBundle(),
                new FilesystemServiceBundle(),
                new DoryServeServiceBundle(new DoryServeConfig($scriptPath, $doryConfig)),
            )
            ->withServerConfig($serverConfig)
            ->routes(RouteGroup::of([
                'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS /{path:any}' => DoryScriptRoute::class,
                'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS /' => DoryScriptRoute::class,
            ]))
            ->build();
    }

    private static function serverConfig(CommandContext $ctx): StoaServerConfig
    {
        $values = $ctx->service(AppContext::class)->values;

        $listen = $ctx->options->get('listen');

        if (is_string($listen) && $listen !== '') {
            [$host, $port] = self::parseListen($listen);
            $values['PHALANX_HOST'] = $host;
            $values['PHALANX_PORT'] = $port;
        }

        $host = $ctx->options->get('host');

        if (is_string($host) && $host !== '') {
            $values['PHALANX_HOST'] = $host;
        }

        $port = $ctx->options->get('port');

        if ($port !== null && $port !== '') {
            $values['PHALANX_PORT'] = (int) $port;
        }

        return StoaServerConfig::fromRuntimeOptions($values);
    }

    /** @return array{string, int} */
    private static function parseListen(string $listen): array
    {
        $separator = strrpos($listen, ':');

        if ($separator === false) {
            throw new RuntimeException("Invalid listen address: {$listen}");
        }

        $host = substr($listen, 0, $separator);
        $port = (int) substr($listen, $separator + 1);

        if ($host === '' || $port <= 0) {
            throw new RuntimeException("Invalid listen address: {$listen}");
        }

        return [$host, $port];
    }

    private static function isStoaMode(CommandContext $ctx, string $scriptPath): bool
    {
        if ($ctx->options->flag('stoa')) {
            return true;
        }

        $projectConfig = DoryProjectConfig::discover(dirname($scriptPath));

        return ($projectConfig->section('serve')['mode'] ?? null) === 'stoa';
    }
}
