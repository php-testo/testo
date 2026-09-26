<?php

declare(strict_types=1);

namespace Testo\Output\JUnit\Internal;

/**
 * Mutable accumulator for a single `<testsuite>` element while events stream
 * in. Counters are `local` (own children) and `total` (rolled up across
 * descendants); the rollup runs once on document generation.
 *
 * @internal
 */
final class JUnitSuiteNode
{
    /** @var list<JUnitSuiteNode> */
    public array $children = [];

    /** @var list<JUnitCaseNode> */
    public array $cases = [];

    public int $tests = 0;
    public int $assertions = 0;
    public int $failures = 0;
    public int $errors = 0;
    public int $skipped = 0;
    public float $time = 0.0;
    public int $totalTests = 0;
    public int $totalAssertions = 0;
    public int $totalFailures = 0;
    public int $totalErrors = 0;
    public int $totalSkipped = 0;
    public float $totalTime = 0.0;

    /**
     * @param non-empty-string $name
     * @param non-empty-string|null $file Optional source-file attribute for the
     *        suite. Set on the class-layer suite so Infection can resolve a
     *        `<testsuite>` to a test file via JUnit instead of reflection.
     * @param \DateTimeInterface|null $startedAt When the suite started; mapped to `timestamp`.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $file = null,
        public readonly ?\DateTimeInterface $startedAt = null,
    ) {}
}
