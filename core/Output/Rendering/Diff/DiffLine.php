<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * A single line of a computed diff, tagged with its {@see DiffOp}.
 *
 * @internal
 */
final readonly class DiffLine
{
    public function __construct(
        public DiffOp $op,
        public string $line,
    ) {}
}
