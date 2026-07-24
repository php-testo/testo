<?php

declare(strict_types=1);

namespace Testo\Bridge\Mockery;

/**
 * Thrown when a Mockery-guarded test starts while another one is still in flight — i.e. under
 * interleaving execution, where sibling tests would clobber each other's mocks.
 *
 * Mockery's mock container is process-global static state and cannot be isolated per fiber, so the
 * bridge serializes Mockery tests. Run them one at a time — {@see \Testo\Fiber\Schedule::Solo} /
 * {@see \Testo\Bridge\Revolt\Strategy::PerTest} (or without fibers at all) — rather than interleaved
 * ({@see \Testo\Fiber\Schedule::RoundRobin}/`Random`, {@see \Testo\Bridge\Revolt\Strategy::PerCase}).
 *
 * @api
 */
final class MockeryConcurrencyException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Mockery cannot run under interleaving test execution: its mock container is process-global '
            . 'and cannot be isolated per fiber, so concurrently running tests would clobber each other\'s '
            . 'mocks. Run Mockery tests one at a time (Schedule::Solo / Strategy::PerTest, or no fibers), '
            . 'not interleaved (Schedule::RoundRobin/Random, Strategy::PerCase).',
        );
    }
}
