<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime\Serve;

use Phalanx\Bia\Runtime\Host\HostFacts;
use RuntimeException;

final class ServeCommand
{
    public function __construct(
        private readonly HostFacts $facts,
    ) {
    }

    public function run(?string $script = null): int
    {
        $serve = $this->facts->serve ?? throw new RuntimeException('[serve] is required for bia serve.');

        if (!class_exists('Swoole\\Http\\Server')) {
            throw new RuntimeException('bia serve requires the Swoole extension bundled into Bia.');
        }

        [$host, $port] = self::listenParts($serve->listen);
        $proxy = TrustedProxy::fromServeFacts($serve);
        $timeoutMs = $this->facts->bia->timeoutMs ?? 30000;
        $drain = self::drainTable();
        $app = $script === null ? null : self::loadApp($script);

        $server = new \Swoole\Http\Server($host, $port);
        $server->set([
            'worker_num' => $this->facts->swoole->eventWorkers,
            'task_worker_num' => $this->facts->swoole->taskWorkers,
        ]);

        $server->on('request', static function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($app, $drain, $proxy, $server, $timeoutMs): void {
            if (bia_shutdown_requested() === 1 || self::isDraining($drain)) {
                self::startDrain($drain, $timeoutMs);
                $response->status(503);
                $response->header('Connection', 'close');
                $response->end("draining\n");

                return;
            }

            if (!self::begin($drain)) {
                $response->status(503);
                $response->end("draining\n");

                return;
            }

            try {
                $trusted = $proxy->project($request->server ?? [], $request->header ?? []);

                if ($app !== null) {
                    $result = $app($trusted, $request, $response);
                    if ($result !== null) {
                        $response->end((string) $result);
                    }

                    return;
                }

                $response->header('Content-Type', 'application/json');
                $response->end(json_encode(['ok' => true, 'request' => $trusted->toArray()], JSON_THROW_ON_ERROR) . "\n");
            } finally {
                self::finish($drain);

                if (self::canShutdown($drain)) {
                    $server->shutdown();
                }
            }
        });

        \Swoole\Timer::tick(100, static function () use ($drain, $server, $timeoutMs): void {
            if (bia_shutdown_requested() !== 1) {
                return;
            }

            self::startDrain($drain, $timeoutMs);

            if (self::canShutdown($drain)) {
                $server->shutdown();
            }
        });

        $server->start();

        return 0;
    }

    private static function drainTable(): \Swoole\Table
    {
        $table = new \Swoole\Table(1);
        $table->column('draining', \Swoole\Table::TYPE_INT, 1);
        $table->column('in_flight', \Swoole\Table::TYPE_INT, 8);
        $table->column('deadline_ms', \Swoole\Table::TYPE_INT, 8);
        $table->create();
        $table->set('state', ['draining' => 0, 'in_flight' => 0, 'deadline_ms' => 0]);

        return $table;
    }

    private static function startDrain(\Swoole\Table $table, int $timeoutMs): void
    {
        $state = $table->get('state');
        if (($state['draining'] ?? 0) === 1) {
            return;
        }

        $table->set('state', [
            'draining' => 1,
            'in_flight' => (int) ($state['in_flight'] ?? 0),
            'deadline_ms' => self::nowMs() + max(1, $timeoutMs),
        ]);
    }

    private static function begin(\Swoole\Table $table): bool
    {
        if (self::isDraining($table)) {
            return false;
        }

        $table->incr('state', 'in_flight');

        return true;
    }

    private static function finish(\Swoole\Table $table): void
    {
        $state = $table->get('state');
        if ((int) ($state['in_flight'] ?? 0) > 0) {
            $table->decr('state', 'in_flight');
        }
    }

    private static function isDraining(\Swoole\Table $table): bool
    {
        $state = $table->get('state');

        return (int) ($state['draining'] ?? 0) === 1;
    }

    private static function canShutdown(\Swoole\Table $table): bool
    {
        $state = $table->get('state');

        if ((int) ($state['draining'] ?? 0) !== 1) {
            return false;
        }

        return (int) ($state['in_flight'] ?? 0) <= 0 || self::nowMs() >= (int) ($state['deadline_ms'] ?? 0);
    }

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /** @return array{0: string, 1: int} */
    private static function listenParts(string $listen): array
    {
        $split = strrpos($listen, ':');
        if ($split === false) {
            throw new RuntimeException("[serve] listen is not host:port: {$listen}");
        }

        return [substr($listen, 0, $split), (int) substr($listen, $split + 1)];
    }

    /** @return callable(TrustedRequest, \\Swoole\\Http\\Request, \\Swoole\\Http\\Response): mixed */
    private static function loadApp(string $script): callable
    {
        if (!is_file($script)) {
            throw new RuntimeException("Serve script not found: {$script}");
        }

        $app = require $script;

        if (!is_callable($app)) {
            throw new RuntimeException("Serve script must return a callable: {$script}");
        }

        return $app;
    }
}
