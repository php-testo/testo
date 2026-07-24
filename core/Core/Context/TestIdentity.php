<?php

declare(strict_types=1);

namespace Testo\Core\Context;

/**
 * Stable identity of a single test within a run.
 *
 * Distinguishes one running test from another — notably when tests execute concurrently (fibers, a real
 * event loop) and their lifecycle events and output interleave on a single stream. Reporters use it to
 * attribute output and lifecycle messages to the right test instead of guessing from ordering.
 *
 * For now it carries only a random numeric id; it may grow more fields later.
 *
 * @api
 */
final readonly class TestIdentity
{
    public function __construct(
        public int $id,
    ) {}

    /**
     * Mint a fresh identity with a random id.
     */
    public static function generate(): self
    {
        return new self(\random_int(1, \PHP_INT_MAX));
    }
}
