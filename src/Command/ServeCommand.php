<?php

declare(strict_types=1);

namespace Phalanx\Bia\Command;

use Phalanx\Console\Command\Arg;
use Phalanx\Console\Command\CommandConfig;
use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Command\DescribesCommand;
use Phalanx\Console\Command\Opt;
use Phalanx\Network\NetworkServiceBundle;
use Phalanx\Boot\AppContext;
use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaExecutionContext;
use Phalanx\Bia\Runtime\BiaProjectConfig;
use Phalanx\Bia\Runtime\BiaScriptRoute;
use Phalanx\Bia\Runtime\BiaServeConfig;
use Phalanx\Bia\Runtime\BiaServeServiceBundle;
use Phalanx\Bia\Runtime\BiaServiceBundle;
use Phalanx\Bia\Runtime\ScriptRunner;
use Phalanx\Filesystem\FilesystemServiceBundle;
use Phalanx\WebSocket\WsServiceBundle;
use Phalanx\HttpClient\HttpServiceBundle;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Http\RouteGroup;
use Phalanx\Http\Http;
use Phalanx\Http\HttpApplication;
use Phalanx\Http\HttpApplicationBuilder;
use Phalanx\Http\HttpServerConfig;
use Phalanx\Task\Scopeable;
use RuntimeException;

class ServeCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $scriptPath = self::resolveScript((string) $ctx->args->required('script'));
        $base = $ctx->service(BiaConfig::class);
        $serverConfig = self::serverConfig($ctx);

        $app = self::isHttpMode($ctx, $scriptPath)
            ? self::loadHttpApplication($ctx, $scriptPath, $base, $serverConfig)
            : self::scriptApplication($ctx, $scriptPath, $base, $serverConfig);

        return $app->run();
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Serve a Bia or Http script over HTTP',
            arguments: [
                Arg::required('script', 'Path to the Bia or Http script'),
            ],
            options: [
                Opt::value('host', '', 'Listen host'),
                Opt::value('port', 'p', 'Listen port'),
                Opt::value('listen', 'l', 'Listen address as host:port'),
                Opt::flag('http', '', 'Treat the script as a Http application bootstrap'),
            ],
            examples: [
                'bia serve app.php',
                'APP_PORT=9000 bia serve app.php',
                'bia serve app.php --listen=127.0.0.1:9000',
                'bia serve app.php --http',
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

    private static function loadHttpApplication(
        CommandContext $ctx,
        string $scriptPath,
        BiaConfig $biaConfig,
        HttpServerConfig $serverConfig,
    ): HttpApplication {
        $result = null;

        /** Suppress bootstrap output so it doesn't leak into the HTTP response. */
        ob_start();
        try {
            $result = $ctx->execute(static function (ExecutionScope $scope) use ($scriptPath, $biaConfig): mixed {
                return ScriptRunner::execute(new BiaExecutionContext(
                    inner: $scope,
                    scriptPath: $scriptPath,
                    config: $biaConfig,
                ));
            });
        } finally {
            ob_end_clean();
        }

        if ($result instanceof HttpApplication) {
            return $result;
        }

        if ($result instanceof HttpApplicationBuilder) {
            return $result->withServerConfig($serverConfig)->build();
        }

        throw new RuntimeException('Http serve mode requires the script to return a HttpApplication or HttpApplicationBuilder.');
    }

    private static function scriptApplication(
        CommandContext $ctx,
        string $scriptPath,
        BiaConfig $biaConfig,
        HttpServerConfig $serverConfig,
    ): HttpApplication {
        $context = $ctx->service(AppContext::class);

        return Http::starting($context->values)
            ->providers(
                new BiaServiceBundle(),
                new HttpServiceBundle(),
                new FilesystemServiceBundle(),
                new NetworkServiceBundle(),
                new WsServiceBundle(),
                new BiaServeServiceBundle(new BiaServeConfig($scriptPath, $biaConfig)),
            )
            ->withServerConfig($serverConfig)
            ->routes(RouteGroup::of([
                'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS /{path:any}' => BiaScriptRoute::class,
                'GET,POST,PUT,PATCH,DELETE,HEAD,OPTIONS /' => BiaScriptRoute::class,
            ]))
            ->build();
    }

    private static function serverConfig(CommandContext $ctx): HttpServerConfig
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

        if (is_string($port) && $port !== '') {
            $values['PHALANX_PORT'] = (int) $port;
        }

        return HttpServerConfig::fromRuntimeOptions($values);
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

    private static function isHttpMode(CommandContext $ctx, string $scriptPath): bool
    {
        if ($ctx->options->flag('http')) {
            return true;
        }

        $projectConfig = BiaProjectConfig::discover(dirname($scriptPath));

        return ($projectConfig->section('serve')['mode'] ?? null) === 'http';
    }
}
