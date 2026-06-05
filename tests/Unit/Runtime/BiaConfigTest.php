<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Runtime\BiaConfig;
use Phalanx\Config\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaConfigTest extends TestCase
{
    #[Test]
    public function defaults_are_sensible(): void
    {
        $config = new BiaConfig();

        self::assertSame(30.0, $config->scriptTimeout);
        self::assertSame(50, $config->maxConcurrency);
        self::assertFalse($config->verbose);
        self::assertTrue($config->configured);
    }

    #[Test]
    public function custom_values_are_accepted(): void
    {
        $config = new BiaConfig(
            scriptTimeout: 60.0,
            maxConcurrency: 100,
            verbose: true,
        );

        self::assertSame(60.0, $config->scriptTimeout);
        self::assertSame(100, $config->maxConcurrency);
        self::assertTrue($config->verbose);
    }

    #[Test]
    public function zero_timeout_fails_validation(): void
    {
        $config = new BiaConfig(scriptTimeout: 0.0);
        $issues = $config->validate(new ValidationContext());

        self::assertCount(1, $issues);
        self::assertSame('bia.script-timeout', $issues[0]->code);
    }

    #[Test]
    public function negative_timeout_fails_validation(): void
    {
        $config = new BiaConfig(scriptTimeout: -1.0);
        $issues = $config->validate(new ValidationContext());

        self::assertCount(1, $issues);
        self::assertSame('bia.script-timeout', $issues[0]->code);
    }

    #[Test]
    public function zero_concurrency_fails_validation(): void
    {
        $config = new BiaConfig(maxConcurrency: 0);
        $issues = $config->validate(new ValidationContext());

        self::assertCount(1, $issues);
        self::assertSame('bia.max-concurrency', $issues[0]->code);
    }

    #[Test]
    public function zero_timeout_marks_not_configured(): void
    {
        $config = new BiaConfig(scriptTimeout: 0.0);

        self::assertFalse($config->configured);
    }

    #[Test]
    public function valid_config_passes_validation(): void
    {
        $config = new BiaConfig();
        $issues = $config->validate(new ValidationContext());

        self::assertCount(0, $issues);
    }

    #[Test]
    public function multiple_errors_accumulate(): void
    {
        $config = new BiaConfig(scriptTimeout: 0.0, maxConcurrency: 0);
        $issues = $config->validate(new ValidationContext());

        self::assertCount(2, $issues);
        $codes = array_map(static fn($i) => $i->code, $issues);
        self::assertContains('bia.script-timeout', $codes);
        self::assertContains('bia.max-concurrency', $codes);
    }
}
