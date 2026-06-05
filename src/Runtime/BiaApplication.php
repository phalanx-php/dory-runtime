<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\AppHost;
use Phalanx\Cancellation\Cancelled;
use Throwable;

class BiaApplication
{
    public function __construct(
        private AppHost $host,
        private ?string $scriptPath,
    ) {
    }

    public function run(): int
    {
        if ($this->scriptPath === null) {
            fwrite(STDERR, "No script path provided.\n");
            return 1;
        }

        if (!file_exists($this->scriptPath)) {
            fwrite(STDERR, "Script not found: {$this->scriptPath}\n");
            return 1;
        }

        $this->host->startup();

        try {
            $scope = $this->host->createScope();

            try {
                return $scope->service(BiaScriptExecutor::class)
                    ->execute($scope, $this->scriptPath);
            } catch (Cancelled $e) {
                throw $e;
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage() . "\n");
                return 1;
            } finally {
                $scope->dispose();
            }
        } finally {
            $this->host->shutdown();
        }
    }
}
