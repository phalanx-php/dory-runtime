<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime\Serve;

final class TrustedRequest
{
    public function __construct(
        private(set) string $scheme,
        private(set) string $host,
        private(set) string $clientIp,
    ) {
    }

    /** @return array{scheme: string, host: string, client_ip: string} */
    public function toArray(): array
    {
        return [
            'scheme' => $this->scheme,
            'host' => $this->host,
            'client_ip' => $this->clientIp,
        ];
    }
}
