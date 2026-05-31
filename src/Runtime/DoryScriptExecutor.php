<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Phalanx\Dory\Rendering\OutputSink;
use Phalanx\Dory\Rendering\ValueRendererPipeline;
use Phalanx\Scope\ExecutionScope;
use RuntimeException;

final class DoryScriptExecutor
{
    public function execute(ExecutionScope $scope, string $scriptPath, ?DoryConfig $config = null): int
    {
        if (!file_exists($scriptPath)) {
            throw new RuntimeException("Script not found: {$scriptPath}");
        }

        $resolved = realpath($scriptPath) ?: $scriptPath;
        $config ??= $scope->service(DoryConfig::class);

        $result = $scope->timeout(
            $config->scriptTimeout,
            static function (ExecutionScope $scriptScope) use ($resolved, $config): mixed {
                return ScriptRunner::execute(new DoryExecutionContext(
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
