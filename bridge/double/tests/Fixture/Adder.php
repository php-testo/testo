<?php

declare(strict_types=1);

namespace Tests\Bridge\Double\Fixture;

/**
 * A two-argument method, so a double can match its call with a joint
 * predicate ({@see \JMac\Testing\Matching\Argument::all()}) that weighs both
 * arguments against each other rather than one position at a time.
 */
interface Adder
{
    public function add(int $a, int $b): int;
}
