<?php

declare(strict_types=1);

namespace Tests\Sandbox\Stub;

/**
 * Cooperative replacement for a blocking `\usleep()`: the timer suspends the calling fiber until its
 * interval has elapsed, so sibling tests on the fiber scheduler keep taking steps while this one
 * "works". The scheduler resumes the fiber every round; the timer simply re-suspends until the
 * monotonic deadline passes.
 */
final class CooperativeTimer
{
    /**
     * @param int $deadline Monotonic deadline in nanoseconds ({@see \hrtime()}).
     */
    private function __construct(
        private readonly int $deadline,
    ) {}

    /**
     * @param int<0, max> $microseconds How long the "work" takes.
     */
    public static function nap(int $microseconds): self
    {
        return new self(\hrtime(true) + $microseconds * 1_000);
    }

    /**
     * Suspend the current fiber — at least once, even for an already-expired timer — until the
     * interval has elapsed.
     */
    public function await(): void
    {
        do {
            \Fiber::suspend();
        } while (\hrtime(true) < $this->deadline);
    }
}
