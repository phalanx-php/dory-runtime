<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Bia\Runtime\Host\HostFacts;

final class BiaConfig
{
    private const int DEFAULT_MAX_CONCURRENCY = 50;

    private const float DEFAULT_SCRIPT_TIMEOUT = 30.0;

    /** computed: validates the minimum script runtime and concurrency limits. */
    public bool $configured {
        get => $this->scriptTimeout > 0 && $this->maxConcurrency > 0;
    }

    public function __construct(
        private(set) float $scriptTimeout = self::DEFAULT_SCRIPT_TIMEOUT,

        private(set) int $maxConcurrency = self::DEFAULT_MAX_CONCURRENCY,

        private(set) bool $verbose = false,

        private(set) bool $embedded = false,
    ) {
    }

    public static function fromHostFacts(HostFacts $facts): self
    {
        return new self(
            scriptTimeout: $facts->bia->timeoutMs === null
                ? self::DEFAULT_SCRIPT_TIMEOUT
                : $facts->bia->timeoutMs / 1000,
            maxConcurrency: $facts->bia->concurrency ?? self::DEFAULT_MAX_CONCURRENCY,
            verbose: $facts->bia->verbose,
            embedded: true,
        );
    }

    /** @return list<BiaConfigIssue> */
    public function validate(): array
    {
        $issues = [];

        if ($this->scriptTimeout <= 0) {
            $issues[] = new BiaConfigIssue(
                'bia.script-timeout',
                'script timeout must be greater than 0.',
                path: 'scriptTimeout',
            );
        }

        if ($this->maxConcurrency < 1) {
            $issues[] = new BiaConfigIssue(
                'bia.max-concurrency',
                'max concurrency must be at least 1.',
                path: 'maxConcurrency',
            );
        }

        return $issues;
    }
}
