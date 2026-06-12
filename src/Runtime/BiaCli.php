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
          bia run <script.php>

        Commands:
          contract  Print the Phalanx bootstrap contract JSON.
          run       Execute a PHP script file.

        TXT);

        return 0;
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
}
