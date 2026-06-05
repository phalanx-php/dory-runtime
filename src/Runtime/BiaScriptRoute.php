<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use GuzzleHttp\Psr7\Response;
use Phalanx\Scope\ExecutionScope;
use Phalanx\Task\Scopeable;

final class BiaScriptRoute implements Scopeable
{
    public function __construct(
        private BiaScriptExecutor $scripts,
        private BiaServeConfig $config,
    ) {
    }

    public function __invoke(ExecutionScope $scope): Response
    {
        ob_start();
        try {
            $code = $this->scripts->execute($scope, $this->config->scriptPath, $this->config->biaConfig);
            $body = (string) ob_get_clean();
        } catch (\Throwable $e) {
            $body = (string) ob_get_clean();
            throw $e;
        }

        return new Response(
            $code === 0 ? 200 : 500,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            $body,
        );
    }
}
