<?php

declare(strict_types=1);

namespace Phalanx\Bia\Rendering;

interface OutputSink
{
    public function line(string $text = ''): void;
}
