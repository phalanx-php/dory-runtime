<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Runtime;

use Phalanx\Dory\Orchestration\AttemptBuilder;
use Phalanx\Dory\Runtime\DoryConfig;
use Phalanx\Dory\Runtime\DoryExecutionContext;
use Phalanx\Dory\Runtime\ScriptContextHolder;
use Phalanx\Dory\Scoped\ScopedFiles;
use Phalanx\Dory\Scoped\ScopedHttpClient;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoryExecutionContextTest extends TestCase
{
    #[Test]
    public function script_name_derives_from_path(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();

        $ctx = new DoryExecutionContext($scope, '/home/zeus/scripts/deploy.php', $config);

        self::assertSame('deploy.php', $ctx->scriptName);
    }

    #[Test]
    public function script_path_is_accessible(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();

        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        self::assertSame('/tmp/test.php', $ctx->scriptPath);
    }

    #[Test]
    public function config_is_accessible(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig(scriptTimeout: 99.0);

        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        self::assertSame(99.0, $ctx->config->scriptTimeout);
    }

    #[Test]
    public function attempt_returns_builder(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        $builder = $ctx->attempt(static fn(): string => 'olympus');

        self::assertInstanceOf(AttemptBuilder::class, $builder);
    }

    #[Test]
    public function println_outputs_to_stdout(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        ob_start();
        $ctx->println('hoplite formation ready');
        $output = ob_get_clean();

        self::assertSame("hoplite formation ready\n", $output);
    }

    #[Test]
    public function dump_outputs_string_values_directly(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        ob_start();
        $ctx->dump('sparta', 'thermopylae');
        $output = ob_get_clean();

        self::assertSame("sparta\nthermopylae\n", $output);
    }

    #[Test]
    public function dump_exports_non_string_values(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        ob_start();
        $ctx->dump(42);
        $output = ob_get_clean();

        self::assertSame("42\n", $output);
    }

    #[Test]
    public function delegates_service_to_inner_scope(): void
    {
        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('service')
            ->with(DoryConfig::class)
            ->willReturn(new DoryConfig());

        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        $ctx->service(DoryConfig::class);
    }

    #[Test]
    public function http_property_returns_scoped_adapter(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        self::assertInstanceOf(ScopedHttpClient::class, $ctx->http);
    }

    #[Test]
    public function fs_property_returns_scoped_adapter(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        self::assertInstanceOf(ScopedFiles::class, $ctx->fs);
    }

    #[Test]
    public function child_closure_installs_child_dory_context(): void
    {
        $childScope = $this->createStub(ExecutionScope::class);
        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('concurrent')
            ->willReturnCallback(static function (callable $task) use ($childScope): array {
                return [$task($childScope)];
            });

        $config = new DoryConfig();
        $ctx = new DoryExecutionContext($scope, '/tmp/test.php', $config);

        ScriptContextHolder::run($ctx, static function () use ($ctx): void {
            $result = $ctx->concurrent(static fn(): bool => dory() !== $ctx);

            self::assertSame([true], $result);
        });
    }
}
