<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Bia\Runtime\BiaProjectConfig;
use Phalanx\Bia\Tests\Fixtures\TemporaryDirectoryTrait;
use Phalanx\Config\ConfigFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaConfigPrecedenceTest extends TestCase
{
    use TemporaryDirectoryTrait;

    #[\Override]
    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
    }

    #[Test]
    public function project_config_supplies_values_when_env_absent(): void
    {
        $project = $this->projectWith(['timeout' => 120, 'concurrency' => 25]);

        $context = $project->contextOverlay();

        $config = ConfigFactory::fromContext($context)->hydrate(BiaConfig::class);

        self::assertSame(120.0, $config->scriptTimeout);
        self::assertSame(25, $config->maxConcurrency);
    }

    #[Test]
    public function env_overrides_project_config(): void
    {
        $project = $this->projectWith(['timeout' => 120]);

        $context = [
            ...$project->contextOverlay(),
            'BIA_SCRIPT_TIMEOUT' => '45',
        ];

        $config = ConfigFactory::fromContext($context)->hydrate(BiaConfig::class);

        self::assertSame(45.0, $config->scriptTimeout);
    }

    #[Test]
    public function env_wins_for_all_bia_config_fields(): void
    {
        $project = $this->projectWith([
            'timeout' => 120,
            'concurrency' => 25,
            'verbose' => false,
        ]);

        $env = [
            'BIA_SCRIPT_TIMEOUT' => '10',
            'BIA_MAX_CONCURRENCY' => '5',
            'BIA_VERBOSE' => 'true',
        ];

        $context = [
            ...$project->contextOverlay(),
            ...$env,
        ];

        $config = ConfigFactory::fromContext($context)->hydrate(BiaConfig::class);

        self::assertSame(10.0, $config->scriptTimeout);
        self::assertSame(5, $config->maxConcurrency);
        self::assertTrue($config->verbose);
    }

    #[Test]
    public function defaults_apply_when_neither_project_nor_env_set(): void
    {
        $project = $this->projectWith([]);

        $config = ConfigFactory::fromContext($project->contextOverlay())
            ->hydrate(BiaConfig::class);

        self::assertSame(30.0, $config->scriptTimeout);
        self::assertSame(50, $config->maxConcurrency);
        self::assertFalse($config->verbose);
    }

    #[Test]
    public function partial_project_config_merges_with_defaults(): void
    {
        $project = $this->projectWith(['timeout' => 90]);

        $config = ConfigFactory::fromContext($project->contextOverlay())
            ->hydrate(BiaConfig::class);

        self::assertSame(90.0, $config->scriptTimeout);
        self::assertSame(50, $config->maxConcurrency);
        self::assertFalse($config->verbose);
    }

    /** @param array<string, mixed> $biaConfig */
    private function projectWith(array $biaConfig): BiaProjectConfig
    {
        $dir = $this->makeTempDir('bia_precedence_');

        $this->writeTempFile('composer.json', json_encode([
            'name' => 'test/precedence',
            'extra' => ['bia' => $biaConfig],
        ]));

        return BiaProjectConfig::discover($dir);
    }
}
