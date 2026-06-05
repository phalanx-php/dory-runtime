<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Config\Config;
use Phalanx\Config\Env;
use Phalanx\Config\Issue;
use Phalanx\Config\IssueLevel;
use Phalanx\Config\ValidationContext;

final class BiaConfig implements Config
{
    private const int DEFAULT_MAX_CONCURRENCY = 50;

    private const float DEFAULT_SCRIPT_TIMEOUT = 30.0;

    /** computed: validates the minimum script runtime and concurrency limits. */
    public bool $configured {
        get => $this->scriptTimeout > 0 && $this->maxConcurrency > 0;
    }

    public function __construct(
        #[Env(key: 'BIA_SCRIPT_TIMEOUT', description: 'Maximum script runtime in seconds')]
        private(set) float $scriptTimeout = self::DEFAULT_SCRIPT_TIMEOUT,

        #[Env(key: 'BIA_MAX_CONCURRENCY', description: 'Maximum concurrent tasks per script')]
        private(set) int $maxConcurrency = self::DEFAULT_MAX_CONCURRENCY,

        #[Env(key: 'BIA_VERBOSE', description: 'Enable verbose script output')]
        private(set) bool $verbose = false,

        #[Env(key: 'BIA_EMBEDDED', description: 'Running inside ripht-hosted binary')]
        private(set) bool $embedded = false,
    ) {
    }

    /** @return list<Issue> */
    public function validate(ValidationContext $context): array
    {
        $issues = [];

        if ($this->scriptTimeout <= 0) {
            $issues[] = new Issue(
                IssueLevel::Error,
                'bia.script-timeout',
                'BIA_SCRIPT_TIMEOUT must be greater than 0.',
                envKey: 'BIA_SCRIPT_TIMEOUT',
                path: 'scriptTimeout',
            );
        }

        if ($this->maxConcurrency < 1) {
            $issues[] = new Issue(
                IssueLevel::Error,
                'bia.max-concurrency',
                'BIA_MAX_CONCURRENCY must be at least 1.',
                envKey: 'BIA_MAX_CONCURRENCY',
                path: 'maxConcurrency',
            );
        }

        return $issues;
    }
}
