<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Bia\Runtime\Host\HostFacts;
use Phalanx\Phalanx;
use Throwable;

final class BiaCli
{
    public function __construct(
        private readonly HostFacts $facts,
    ) {
    }

    public static function fromNativeFacts(array $payload): self
    {
        return new self(HostFacts::hydrate($payload));
    }

    /** @param list<string> $argv */
    public static function fromArgv(array $argv): self
    {
        return new self(new HostFacts(
            contract: HostFacts::CONTRACT,
            embedded: false,
            argv: array_values($argv),
            appEnv: Host\AppEnv::Dev,
            env: self::processEnvFacts(),
            php: new Host\PhpFacts((string) ini_get('memory_limit'), []),
            swoole: new Host\SwooleFacts(1, 0, []),
            serve: null,
            bia: new Host\BiaFacts(null, null, false),
            paths: new Host\PathFacts(getcwd() ?: '.', getcwd() ?: '.', []),
        ));
    }

    public function run(): int
    {
        $argv = $this->facts->argv;
        $command = $argv[0] ?? 'help';

        return match ($command) {
            'contract', 'bootstrap-contract' => $this->contract(),
            'env:check' => $this->envCheck(array_slice($argv, 1)),
            'env:example' => $this->envExample(array_slice($argv, 1)),
            'run' => $this->runScript($argv[1] ?? null),
            'help', '-h', '--help' => $this->help(),
            default => $this->unknown($command),
        };
    }

    private function contract(): int
    {
        fwrite(STDOUT, json_encode(Phalanx::bootstrapContract()->toArray(), JSON_THROW_ON_ERROR) . PHP_EOL);

        return 0;
    }

    private function help(): int
    {
        fwrite(STDOUT, <<<'TXT'
        Usage:
          bia contract
          bia env:check [config.php...]
          bia env:example [config.php...]
          bia run <script.php>

        Commands:
          contract  Print the Phalanx bootstrap contract JSON.
          env:check Replay config files and report env problems.
          env:example Print demanded env keys as .env.example lines.
          run       Execute a PHP script file.

        TXT);

        return 0;
    }

    /** @param list<string> $files */
    private function envCheck(array $files): int
    {
        $context = $this->replayEnv($files);
        $failed = false;

        foreach ($this->facts->env->issues as $issue) {
            $source = $issue['source'] ?? 'env';
            $line = $issue['line'] === null ? '' : ':' . $issue['line'];
            fwrite(STDERR, "{$source}{$line} {$issue['severity']}: {$issue['message']}\n");
            $failed = $failed || $issue['severity'] === 'error';
        }

        foreach ($context->demands() as $demand) {
            if (!$demand->required || array_key_exists($demand->key, $this->facts->env->values)) {
                continue;
            }

            $failed = true;
            $suggestion = $this->closestKey($demand->key);
            fwrite(STDERR, "{$demand->source}\n  {$demand->key} missing");
            if ($suggestion !== null) {
                fwrite(STDERR, " - did you mean {$suggestion}?");
            }
            fwrite(STDERR, "\n");
        }

        if (!$failed) {
            fwrite(STDOUT, "env ok\n");
        }

        return $failed ? 1 : 0;
    }

    /** @param list<string> $files */
    private function envExample(array $files): int
    {
        $context = $this->replayEnv($files);
        $keys = [];

        foreach ($context->demands() as $demand) {
            $keys[$demand->key] = true;
        }

        ksort($keys);
        foreach (array_keys($keys) as $key) {
            fwrite(STDOUT, "{$key}=\n");
        }

        return 0;
    }

    /** @param list<string> $files */
    private function replayEnv(array $files): EnvContext
    {
        $context = new EnvContext($this->facts->env);

        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new \RuntimeException("Config file not found: {$file}");
            }

            (static function (EnvContext $context, string $file): mixed {
                return require $file;
            })($context, $file);
        }

        return $context;
    }

    private function runScript(?string $script): int
    {
        if ($script === null || $script === '') {
            fwrite(STDERR, "Missing script path.\n");

            return 2;
        }

        if (!is_file($script)) {
            fwrite(STDERR, "Script not found: {$script}\n");

            return 1;
        }

        $config = BiaConfig::fromHostFacts($this->facts);
        $issues = $config->validate();

        if ($issues !== []) {
            foreach ($issues as $issue) {
                fwrite(STDERR, "{$issue->code}: {$issue->message}\n");
            }

            return 1;
        }

        try {
            $result = require $script;
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage() . "\n");

            return 1;
        }

        return is_int($result) ? $result : 0;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, "Unknown Bia command: {$command}\n");
        $this->help();

        return 2;
    }

    private static function processEnvFacts(): Host\EnvFacts
    {
        $values = [];
        $environment = getenv();
        $environment = is_array($environment) ? $environment : [];

        foreach ($environment as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            $values[$key] = [
                'value' => (string) $value,
                'source' => 'process env',
                'line' => null,
            ];
        }

        return new Host\EnvFacts(['process env'], $values, []);
    }

    private function closestKey(string $wanted): ?string
    {
        $closest = null;
        $distance = PHP_INT_MAX;
        foreach (array_keys($this->facts->env->values) as $key) {
            $current = levenshtein($wanted, $key);
            if ($current < $distance) {
                $closest = $key;
                $distance = $current;
            }
        }

        return $distance <= 3 ? $closest : null;
    }
}
