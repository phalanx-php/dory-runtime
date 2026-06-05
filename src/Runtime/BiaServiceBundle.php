<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Boot\AppContext;
use Phalanx\Bia\Code\CodeParser;
use Phalanx\Bia\Code\NativeCodeParser;
use Phalanx\Bia\Rendering\EchoSink;
use Phalanx\Bia\Rendering\OutputSink;
use Phalanx\Bia\Rendering\SettlementRenderer;
use Phalanx\Bia\Rendering\ThrowableRenderer;
use Phalanx\Bia\Rendering\ValueRendererPipeline;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;

class BiaServiceBundle extends ServiceBundle
{
    #[\Override]
    public static function configs(): array
    {
        return [BiaConfig::class];
    }

    public function services(Services $services, AppContext $context): void
    {
        $cwd = getcwd() ?: '.';

        $services->singleton(AppContext::class)
            ->factory(static fn(): AppContext => $context);

        $services->singleton(BiaProjectConfig::class)
            ->factory(static fn(): BiaProjectConfig => BiaProjectConfig::discover($cwd));

        $services->singleton(ValueRendererPipeline::class)
            ->factory(static fn(): ValueRendererPipeline => new ValueRendererPipeline([
                new SettlementRenderer(),
                new ThrowableRenderer(),
            ]));

        $services->singleton(BiaScriptExecutor::class)
            ->factory(static fn(): BiaScriptExecutor => new BiaScriptExecutor());

        $services->singleton(CodeParser::class)
            ->factory(static fn(): CodeParser => new NativeCodeParser());

        $services->scoped(OutputSink::class)
            ->factory(static fn(): OutputSink => new EchoSink());
    }
}
