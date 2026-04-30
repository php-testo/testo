<?php

declare(strict_types=1);

namespace Testo\Codecov\Result;

/**
 * Coverage data for a single source line.
 *
 * Carries the line's coverage status and the list of tests that executed it.
 * Per-test attribution is populated by {@see \Testo\Codecov\Internal\Middleware\CoverageTestInterceptor}
 * via {@see CoverageResult::withTestMethod()} and merged across runs by {@see self::merge()}.
 */
final readonly class LineCoverage
{
    public function __construct(
        /** @var int<0, max> */
        public int $line,
        public LineStatus $status,

        /** @var list<non-empty-string> Tests that executed this line, in insertion order. */
        public array $testMethods = [],
    ) {}

    /**
     * Merges another line entry into this one.
     *
     * Status follows the OR rule: any {@see LineStatus::Executed} wins.
     * Test methods are unioned, preserving insertion order.
     */
    public function merge(self $other): self
    {
        $status = match (true) {
            $this->status === LineStatus::Executed,
            $other->status === LineStatus::Executed => LineStatus::Executed,
            default => $this->status,
        };

        $testMethods = $this->testMethods;
        foreach ($other->testMethods as $method) {
            \in_array($method, $testMethods, true) or $testMethods[] = $method;
        }

        return new self($this->line, $status, $testMethods);
    }

    /**
     * Returns a copy with the given test method recorded as a covering test.
     *
     * No-op if the line is not {@see LineStatus::Executed} or the method is already recorded.
     */
    public function withTestMethod(string $method): self
    {
        if ($this->status !== LineStatus::Executed) {
            return $this;
        }
        if (\in_array($method, $this->testMethods, true)) {
            return $this;
        }

        return new self($this->line, $this->status, [...$this->testMethods, $method]);
    }
}
