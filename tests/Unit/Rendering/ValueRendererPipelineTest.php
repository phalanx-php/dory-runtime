<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Rendering;

use Phalanx\Bia\Rendering\OutputSink;
use Phalanx\Bia\Rendering\ValueRenderer;
use Phalanx\Bia\Rendering\ValueRendererPipeline;
use Phalanx\Bia\Tests\Fixtures\BufferSink;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValueRendererPipelineTest extends TestCase
{
    #[Test]
    public function first_matching_renderer_wins(): void
    {
        $first = new class implements ValueRenderer {
            public function supports(mixed $value): bool
            {
                return is_string($value);
            }

            public function render(mixed $value, OutputSink $output): void
            {
                $output->line('first: ' . $value);
            }
        };

        $second = new class implements ValueRenderer {
            public function supports(mixed $value): bool
            {
                return is_string($value);
            }

            public function render(mixed $value, OutputSink $output): void
            {
                $output->line('second: ' . $value);
            }
        };

        $pipeline = new ValueRendererPipeline([$first, $second]);
        $sink = new BufferSink();

        $pipeline->render('agora', $sink);

        self::assertSame(['first: agora'], $sink->lines);
    }

    #[Test]
    public function falls_back_to_var_dumper_when_no_renderer_matches(): void
    {
        $renderer = new class implements ValueRenderer {
            public function supports(mixed $value): bool
            {
                return false;
            }

            public function render(mixed $value, OutputSink $output): void
            {
            }
        };

        $pipeline = new ValueRendererPipeline([$renderer]);
        $sink = new BufferSink();

        $pipeline->render('doru', $sink);

        self::assertStringContainsString('doru', $sink->lines[0]);
    }

    #[Test]
    public function empty_renderer_list_uses_var_dumper_fallback(): void
    {
        $pipeline = new ValueRendererPipeline([]);
        $sink = new BufferSink();

        $pipeline->render(42, $sink);

        self::assertStringContainsString('42', $sink->lines[0]);
    }

    #[Test]
    public function var_dumper_fallback_handles_array(): void
    {
        $pipeline = new ValueRendererPipeline([]);
        $sink = new BufferSink();

        $pipeline->render(['sarissa'], $sink);

        self::assertStringContainsString('sarissa', $sink->lines[0]);
    }

    #[Test]
    public function skips_non_matching_renderer_and_uses_next(): void
    {
        $nonMatching = new class implements ValueRenderer {
            public function supports(mixed $value): bool
            {
                return false;
            }

            public function render(mixed $value, OutputSink $output): void
            {
                $output->line('should not appear');
            }
        };

        $matching = new class implements ValueRenderer {
            public function supports(mixed $value): bool
            {
                return is_int($value);
            }

            public function render(mixed $value, OutputSink $output): void
            {
                $output->line('matched: ' . $value);
            }
        };

        $pipeline = new ValueRendererPipeline([$nonMatching, $matching]);
        $sink = new BufferSink();

        $pipeline->render(7, $sink);

        self::assertSame(['matched: 7'], $sink->lines);
    }
}
