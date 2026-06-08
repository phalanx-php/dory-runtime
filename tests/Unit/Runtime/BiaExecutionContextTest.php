<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Orchestration\AttemptBuilder;
use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaExecutionContext;
use Phalanx\Bia\Runtime\ScriptContextHolder;
use Phalanx\Bia\Scoped\ScopedCode;
use Phalanx\Bia\Scoped\ScopedFiles;
use Phalanx\Bia\Scoped\ScopedHttpClient;
use Phalanx\Mark\Mark;
use Phalanx\Scope\ExecutionScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaExecutionContextTest extends TestCase
{
    #[Test]
    public function script_name_derives_from_path(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();

        $ctx = new BiaExecutionContext($scope, '/home/zeus/scripts/deploy.php', $config);

        self::assertSame('deploy.php', $ctx->scriptName);
    }

    #[Test]
    public function script_path_is_accessible(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();

        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        self::assertSame('/tmp/test.php', $ctx->scriptPath);
    }

    #[Test]
    public function config_is_accessible(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig(scriptTimeout: 99.0);

        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        self::assertSame(99.0, $ctx->config->scriptTimeout);
    }

    #[Test]
    public function attempt_returns_builder(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        $builder = $ctx->attempt(static fn(): string => 'olympus');

        self::assertInstanceOf(AttemptBuilder::class, $builder);
    }

    #[Test]
    public function println_outputs_to_stdout(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        ob_start();
        $ctx->println('hoplite formation ready');
        $output = ob_get_clean();

        self::assertSame("hoplite formation ready\n", $output);
    }

    #[Test]
    public function dump_outputs_string_values_directly(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        ob_start();
        $ctx->dump('sparta', 'thermopylae');
        $output = ob_get_clean();

        self::assertSame("sparta\nthermopylae\n", $output);
    }

    #[Test]
    public function dump_exports_non_string_values(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

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
            ->with(BiaConfig::class)
            ->willReturn(new BiaConfig());

        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        $ctx->service(BiaConfig::class);
    }

    #[Test]
    public function delay_accepts_numeric_seconds_for_scripts(): void
    {
        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('delay')
            ->with(self::callback(
                static fn(Mark $duration): bool => $duration->toMilliseconds() === 250,
            ));

        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        $ctx->delay(0.25);
    }

    #[Test]
    public function http_property_returns_scoped_adapter(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        self::assertInstanceOf(ScopedHttpClient::class, $ctx->http);
    }

    #[Test]
    public function fs_property_returns_scoped_adapter(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        self::assertInstanceOf(ScopedFiles::class, $ctx->fs);
    }

    #[Test]
    public function code_property_returns_scoped_adapter(): void
    {
        $scope = $this->createStub(ExecutionScope::class);
        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        self::assertInstanceOf(ScopedCode::class, $ctx->code);
    }

    #[Test]
    public function child_closure_installs_child_bia_context(): void
    {
        $childScope = $this->createStub(ExecutionScope::class);
        $scope = $this->createMock(ExecutionScope::class);
        $scope->expects(self::once())
            ->method('concurrent')
            ->willReturnCallback(static function (callable $task) use ($childScope): array {
                return [$task($childScope)];
            });

        $config = new BiaConfig();
        $ctx = new BiaExecutionContext($scope, '/tmp/test.php', $config);

        ScriptContextHolder::run($ctx, static function () use ($ctx): void {
            $result = $ctx->concurrent(static fn(): bool => bia() !== $ctx);

            self::assertSame([true], $result);
        });
    }
}
