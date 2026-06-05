<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Bia\Rendering\OutputSink;
use Phalanx\Bia\Rendering\ValueRendererPipeline;
use Phalanx\Scope\ExecutionScope;
use RuntimeException;

final class BiaScriptExecutor
{
    public function execute(ExecutionScope $scope, string $scriptPath, ?BiaConfig $config = null): int
    {
        if (!file_exists($scriptPath)) {
            throw new RuntimeException("Script not found: {$scriptPath}");
        }

        $resolved = realpath($scriptPath) ?: $scriptPath;
        $config ??= $scope->service(BiaConfig::class);

        $result = $scope->timeout(
            $config->scriptTimeout,
            static function (ExecutionScope $scriptScope) use ($resolved, $config): mixed {
                return ScriptRunner::execute(new BiaExecutionContext(
                    inner: $scriptScope,
                    scriptPath: $resolved,
                    config: $config,
                ));
            },
        );

        if (is_int($result)) {
            return $result;
        }

        if ($result !== null) {
            $scope->service(ValueRendererPipeline::class)
                ->render($result, $scope->service(OutputSink::class));
        }

        return 0;
    }
}
