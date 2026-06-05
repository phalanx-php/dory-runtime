<?php

declare(strict_types=1);

namespace Phalanx\Bia\Rendering;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

class ValueRendererPipeline
{
    private ?VarCloner $cloner = null;
    private ?CliDumper $dumper = null;

    /** @param list<ValueRenderer> $renderers */
    public function __construct(private array $renderers)
    {
    }

    public function render(mixed $value, OutputSink $output): void
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($value)) {
                $renderer->render($value, $output);

                return;
            }
        }

        $output->line($this->varDump($value));
    }

    private function varDump(mixed $value): string
    {
        $this->cloner ??= new VarCloner();
        $this->dumper ??= new CliDumper();

        $data = $this->cloner->cloneVar($value);

        return rtrim($this->dumper->dump($data, true) ?: '');
    }
}
