<?php

declare(strict_types=1);

namespace Testo\Output\JUnit\Internal;

use Testo\Core\Value\Status;

/**
 * Captured `<testcase>` payload produced from a single `TestResult`.
 *
 * @internal
 */
final readonly class JUnitCaseNode
{
    public function __construct(
        /** @var non-empty-string */
        public string $name,
        public string $classname,
        public ?string $file,
        public ?int $line,
        public float $time,
        public Status $status,
        public ?JUnitCaseOutcome $outcome,

        /**
         * Index of the data-provider this row was produced from. Null for
         * single-provider tests.
         * Mapped to `testo:data-provider` on `<testcase>`.
         */
        public ?int $providerIndex = null,

        /**
         * Zero-based position of this dataset within its provider. Null when
         * the testcase isn't a data-provider row (regular pipeline test).
         * Mapped to `testo:data-set` on `<testcase>`.
         */
        public ?int $datasetIndex = null,

        /**
         * Human-readable dataset label from the `yield <key> => …` form, kept
         * as-is for diagnostics. Filtering goes through the integer index.
         * Mapped to `testo:data-set-key` on `<testcase>` when set.
         */
        public string|int|null $datasetKey = null,

        /**
         * Named numeric measurements to emit as `<properties>` inside the `<testcase>` — currently the
         * benchmark counters, for tests that are benchmarks. Empty for an ordinary test, in which case
         * no `<properties>` element is written at all.
         *
         * @var array<non-empty-string, int|float>
         */
        public array $properties = [],
    ) {}
}
