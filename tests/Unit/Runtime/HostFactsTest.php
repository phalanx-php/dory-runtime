<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use InvalidArgumentException;
use Phalanx\Bia\Runtime\Host\AppEnv;
use Phalanx\Bia\Runtime\Host\HostFacts;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HostFactsTest extends TestCase
{
    #[Test]
    public function hydrates_complete_native_handoff_payload(): void
    {
        $facts = HostFacts::hydrate(self::payload());

        self::assertSame(HostFacts::CONTRACT, $facts->contract);
        self::assertTrue($facts->embedded);
        self::assertSame(['bia', 'serve', 'app.php'], $facts->argv);
        self::assertSame(AppEnv::Dev, $facts->appEnv);
        self::assertSame('128M', $facts->php->memoryLimit);
        self::assertSame(1, $facts->swoole->eventWorkers);
        self::assertSame('127.0.0.1:9000', $facts->serve?->listen);
        self::assertSame(2500, $facts->bia->timeoutMs);
        self::assertSame('/tmp/bia-runtime', $facts->paths->runtimeDir);
    }

    #[Test]
    public function rejects_unknown_keys(): void
    {
        $payload = self::payload();
        $payload['extra'] = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HostFacts unknown keys: extra');

        HostFacts::hydrate($payload);
    }

    #[Test]
    public function rejects_missing_keys(): void
    {
        $payload = self::payload();
        unset($payload['php']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HostFacts missing keys: php');

        HostFacts::hydrate($payload);
    }

    #[Test]
    public function rejects_contract_mismatch_with_actionable_message(): void
    {
        $payload = self::payload();
        $payload['contract'] = 99;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("handoff contract mismatch: host speaks v99, runtime expects v1\n-> upgrade bia or pin phalanx-php/phalanx");

        HostFacts::hydrate($payload);
    }

    #[Test]
    public function round_trips_to_array(): void
    {
        $payload = self::payload();

        self::assertSame($payload, HostFacts::hydrate($payload)->toArray());
    }

    /** @return array<string, mixed> */
    private static function payload(): array
    {
        return [
            'contract' => HostFacts::CONTRACT,
            'embedded' => true,
            'argv' => ['bia', 'serve', 'app.php'],
            'app_env' => 'dev',
            'php' => [
                'memory_limit' => '128M',
                'ini' => [
                    'display_errors' => '1',
                ],
            ],
            'swoole' => [
                'event_workers' => 1,
                'task_workers' => 0,
                'hooks' => ['curl'],
            ],
            'serve' => [
                'listen' => '127.0.0.1:9000',
            ],
            'bia' => [
                'timeout_ms' => 2500,
                'concurrency' => 5,
                'verbose' => true,
            ],
            'paths' => [
                'runtime_dir' => '/tmp/bia-runtime',
                'cwd' => '/tmp/project',
                'watch' => ['/tmp/project/app'],
            ],
        ];
    }
}
