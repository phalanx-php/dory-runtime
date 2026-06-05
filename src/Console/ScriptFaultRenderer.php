<?php

declare(strict_types=1);

namespace Phalanx\Bia\Console;

use Phalanx\Console\Command\CommandContext;
use Phalanx\Console\Console\ConsoleErrorRenderer;
use Phalanx\Console\Console\Output\StreamOutput;
use Phalanx\Console\Console\Style\Style;
use Phalanx\Console\Console\Style\Theme;
use Phalanx\Console\Console\Widget\SourcePreview;
use Phalanx\Bia\Exception\ScriptFault;
use Phalanx\Bia\Runtime\ScriptRunner;
use Throwable;

final readonly class ScriptFaultRenderer implements ConsoleErrorRenderer
{
    private const string MARGIN = '  ';

    public function render(CommandContext $ctx, Throwable $e, StreamOutput $output): bool
    {
        $fault = self::scriptFault($e);

        if ($fault === null) {
            return false;
        }

        $theme = $ctx->service(Theme::class);
        $scriptLine = $fault->scriptLine();

        $output->persist("\n");
        $output->persist(self::MARGIN . Style::new()->bg('red')->fg('white')->bold()->apply(' ERROR '));
        $output->persist(self::MARGIN . $theme->error->apply($fault->getMessage()));
        $output->persist(
            "\n" . self::MARGIN
            . $theme->muted->apply('Source: ')
            . $theme->accent->apply($fault->scriptLabel . ':' . $scriptLine)
        );

        $output->persist($this->sourcePreview($theme, $fault));

        $filtered = $this->filterTrace($fault);

        if ($filtered !== []) {
            $output->persist(self::MARGIN . $theme->muted->apply('Stack Trace:'));
            $this->renderTrace($output, $theme, $fault, $filtered);
        }

        $output->persist("\n");

        return true;
    }

    private static function scriptFault(Throwable $e): ?ScriptFault
    {
        do {
            if ($e instanceof ScriptFault) {
                return $e;
            }

            $e = $e->getPrevious();
        } while ($e !== null);

        return null;
    }

    private function sourcePreview(Theme $theme, ScriptFault $e): string
    {
        if ($e->isInline) {
            return (new SourcePreview($theme, contextLines: 0))
                ->render($e->sourceFile, $e->getPrevious()->getLine());
        }

        return (new SourcePreview($theme))->render($e->sourceFile, $e->scriptLine());
    }

    /** @return list<array<string, mixed>> */
    private function filterTrace(ScriptFault $e): array
    {
        $cause = $e->getPrevious();
        $sourceFile = $cause->getFile();
        $filtered = [];

        foreach ($cause->getTrace() as $frame) {
            if (($frame['class'] ?? '') === ScriptRunner::class) {
                break;
            }

            $file = $frame['file'] ?? '';

            if ($file === $sourceFile) {
                $filtered[] = $frame;
            }
        }

        return $filtered;
    }

    /** @param list<array<string, mixed>> $frames */
    private function renderTrace(StreamOutput $output, Theme $theme, ScriptFault $e, array $frames): void
    {
        $funcStyle = Style::new()->fg('yellow');

        foreach ($frames as $i => $frame) {
            $line = ($frame['line'] ?? 0) - $e->lineOffset;
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $func = $frame['function'];

            $output->persist(sprintf(
                "%s %s %s() %s %s\n",
                self::MARGIN,
                $theme->muted->apply(sprintf('%d.', $i + 1)),
                $funcStyle->apply($class . $type . $func),
                $theme->muted->apply('at'),
                $theme->accent->apply($e->scriptLabel . ':' . $line),
            ));
        }
    }
}
