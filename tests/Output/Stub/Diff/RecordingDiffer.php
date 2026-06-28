<?php

declare(strict_types=1);

namespace Tests\Output\Stub\Diff;

use Testo\Output\Rendering\Diff\Differ;
use Testo\Output\Rendering\Diff\DiffLine;

/**
 * A spy {@see Differ} that records every `diff()` call (with its arguments) and delegates the work to
 * a wrapped differ. Used to assert how decorators/composites drive their inner differ — e.g. that
 * {@see \Testo\Output\Rendering\Diff\PrefixSuffixDiffer} only hands the changed middle to its inner,
 * or that {@see \Testo\Output\Rendering\Diff\PatienceDiffer} falls back when it has no anchor.
 */
final class RecordingDiffer implements Differ
{
    /**
     * Arguments of every recorded call, in order.
     *
     * @var list<array{string, string}>
     */
    public array $calls = [];

    public function __construct(
        private readonly Differ $delegate,
    ) {}

    /**
     * @return list<DiffLine>
     */
    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $this->calls[] = [$expected, $actual];

        return $this->delegate->diff($expected, $actual);
    }
}
