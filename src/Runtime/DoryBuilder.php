<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Phalanx\Application;
use Phalanx\ApplicationBuilder;
use Phalanx\Argos\NetworkServiceBundle;
use Phalanx\Boot\AppContext;
use Phalanx\Grammata\FilesystemServiceBundle;
use Phalanx\Hermes\WsServiceBundle;
use Phalanx\Iris\HttpServiceBundle;
use Phalanx\Middleware\ServiceTransformationMiddleware;
use Phalanx\Middleware\TaskMiddleware;
use Phalanx\Service\ServiceBundle;

class DoryBuilder
{
    private ApplicationBuilder $app;
    private ?string $scriptPath = null;

    public function __construct(AppContext $context = new AppContext())
    {
        $projectConfig = DoryProjectConfig::discover(getcwd() ?: '.');

        $merged = new AppContext([
            ...$projectConfig->contextOverlay(),
            ...$context->values,
        ]);

        $this->app = Application::starting($merged->values);
        $this->app->providers(new DoryServiceBundle());

        $this->autoDetectModules();
    }

    public function providers(ServiceBundle ...$providers): self
    {
        $this->app->providers(...$providers);
        return $this;
    }

    public function serviceMiddleware(ServiceTransformationMiddleware ...$middlewares): self
    {
        $this->app->serviceMiddleware(...$middlewares);
        return $this;
    }

    public function taskMiddleware(TaskMiddleware ...$middlewares): self
    {
        $this->app->taskMiddleware(...$middlewares);
        return $this;
    }

    public function script(string $path): self
    {
        $this->scriptPath = $path;
        return $this;
    }

    public function build(): DoryApplication
    {
        $host = $this->app->compile();

        return new DoryApplication(
            host: $host,
            scriptPath: $this->scriptPath,
        );
    }

    public function run(): int
    {
        return $this->build()->run();
    }

    private function autoDetectModules(): void
    {
        if (class_exists(HttpServiceBundle::class)) {
            $this->app->providers(new HttpServiceBundle());
        }

        if (class_exists(FilesystemServiceBundle::class)) {
            $this->app->providers(new FilesystemServiceBundle());
        }

        if (class_exists(NetworkServiceBundle::class)) {
            $this->app->providers(new NetworkServiceBundle());
        }

        if (class_exists(WsServiceBundle::class)) {
            $this->app->providers(new WsServiceBundle());
        }
    }
}
