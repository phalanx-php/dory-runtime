<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime\Serve;

use Phalanx\Bia\Runtime\Serve\TrustedProxy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TrustedProxyTest extends TestCase
{
    #[Test]
    public function untrusted_proxy_headers_are_ignored(): void
    {
        $request = (new TrustedProxy([]))->project(
            ['remote_addr' => '10.0.0.5', 'server_port' => 80],
            [
                'host' => 'app.internal',
                'x-forwarded-for' => '203.0.113.10',
                'x-forwarded-proto' => 'https',
                'x-forwarded-host' => 'public.example',
            ],
        );

        self::assertSame('http', $request->scheme);
        self::assertSame('app.internal', $request->host);
        self::assertSame('10.0.0.5', $request->clientIp);
    }

    #[Test]
    public function nginx_preset_trusts_forwarded_header_family(): void
    {
        $request = (new TrustedProxy(['x-forwarded-for', 'x-forwarded-host', 'x-forwarded-proto']))->project(
            ['remote_addr' => '10.0.0.5', 'server_port' => 80],
            [
                'host' => 'app.internal',
                'x-forwarded-for' => '203.0.113.10, 10.0.0.5',
                'x-forwarded-proto' => 'https',
                'x-forwarded-host' => 'public.example',
            ],
        );

        self::assertSame('https', $request->scheme);
        self::assertSame('public.example', $request->host);
        self::assertSame('203.0.113.10', $request->clientIp);
    }

    #[Test]
    public function cloudflare_preset_prefers_cloudflare_client_header(): void
    {
        $request = (new TrustedProxy(['cf-connecting-ip', 'cf-visitor', 'x-forwarded-for']))->project(
            ['remote_addr' => '10.0.0.5', 'server_port' => 80],
            [
                'host' => 'app.example',
                'cf-connecting-ip' => '198.51.100.7',
                'cf-visitor' => '{"scheme":"https"}',
                'x-forwarded-for' => '203.0.113.10',
            ],
        );

        self::assertSame('https', $request->scheme);
        self::assertSame('app.example', $request->host);
        self::assertSame('198.51.100.7', $request->clientIp);
    }
}
