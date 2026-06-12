<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime\Serve;

use Phalanx\Bia\Runtime\Host\ServeFacts;

final class TrustedProxy
{
    /** @param list<string> $trustedHeaders */
    public function __construct(
        private readonly array $trustedHeaders,
    ) {
    }

    public static function fromServeFacts(ServeFacts $facts): self
    {
        return new self($facts->trustedHeaders);
    }

    /** @param array<string, scalar> $server @param array<string, scalar> $headers */
    public function project(array $server, array $headers): TrustedRequest
    {
        $headers = self::normalize($headers);

        return new TrustedRequest(
            scheme: $this->trustedScheme($server, $headers),
            host: $this->trustedHost($server, $headers),
            clientIp: $this->trustedClientIp($server, $headers),
        );
    }

    /** @param array<string, scalar> $headers @return array<string, string> */
    private static function normalize(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            $normalized[strtolower(str_replace('_', '-', (string) $key))] = (string) $value;
        }

        return $normalized;
    }

    /** @param array<string, scalar> $server @param array<string, string> $headers */
    private function trustedScheme(array $server, array $headers): string
    {
        if ($this->trusts('cf-visitor') && isset($headers['cf-visitor'])) {
            $visitor = json_decode($headers['cf-visitor'], true);
            if (is_array($visitor) && ($visitor['scheme'] ?? null) === 'https') {
                return 'https';
            }
        }

        if ($this->trusts('x-forwarded-proto') && isset($headers['x-forwarded-proto'])) {
            return self::firstHeaderValue($headers['x-forwarded-proto']) === 'https' ? 'https' : 'http';
        }

        if (($server['https'] ?? '') === 'on' || (string) ($server['server_port'] ?? '') === '443') {
            return 'https';
        }

        return 'http';
    }

    /** @param array<string, scalar> $server @param array<string, string> $headers */
    private function trustedHost(array $server, array $headers): string
    {
        if ($this->trusts('x-forwarded-host') && isset($headers['x-forwarded-host'])) {
            return self::firstHeaderValue($headers['x-forwarded-host']);
        }

        return $headers['host'] ?? (string) ($server['http_host'] ?? 'localhost');
    }

    /** @param array<string, scalar> $server @param array<string, string> $headers */
    private function trustedClientIp(array $server, array $headers): string
    {
        if ($this->trusts('cf-connecting-ip') && isset($headers['cf-connecting-ip'])) {
            return $headers['cf-connecting-ip'];
        }

        if ($this->trusts('x-forwarded-for') && isset($headers['x-forwarded-for'])) {
            return self::firstHeaderValue($headers['x-forwarded-for']);
        }

        return (string) ($server['remote_addr'] ?? '127.0.0.1');
    }

    private function trusts(string $header): bool
    {
        return in_array($header, $this->trustedHeaders, true);
    }

    private static function firstHeaderValue(string $value): string
    {
        return trim(explode(',', $value, 2)[0]);
    }
}
