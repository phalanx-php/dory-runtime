<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Application;
use Phalanx\ApplicationBuilder;
use Phalanx\Boot\AppContext;
use Phalanx\Filesystem\FilesystemServiceBundle;
use Phalanx\HttpClient\Bundle as HttpClientBundle;
use Phalanx\Middleware\ServiceTransformationMiddleware;
use Phalanx\Middleware\TaskMiddleware;
use Phalanx\Network\NetworkServiceBundle;
use Phalanx\Service\ServiceBundle;
use Phalanx\WebSocket\Bundle as WebSocketBundle;

class BiaBuilder
{
    private ApplicationBuilder $app;
    private ?string $scriptPath = null;

    public function __construct(AppContext $context = new AppContext())
    {
        $projectConfig = BiaProjectConfig::discover(getcwd() ?: '.');

        $merged = new AppContext([
            ...$projectConfig->contextOverlay(),
            ...$context->values,
        ]);

        $this->app = Application::starting($merged->values);
        $this->app->providers(new BiaServiceBundle());

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

    public function build(): BiaApplication
    {
        $host = $this->app->compile();

        return new BiaApplication(
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
        if (class_exists(HttpClientBundle::class)) {
            $this->app->providers(new HttpClientBundle());
        }

        if (class_exists(FilesystemServiceBundle::class)) {
            $this->app->providers(new FilesystemServiceBundle());
        }

        if (class_exists(NetworkServiceBundle::class)) {
            $this->app->providers(new NetworkServiceBundle());
        }

        if (class_exists(WebSocketBundle::class)) {
            $this->app->providers(new WebSocketBundle());
        }
    }
}
