<?php

declare(strict_types=1);

namespace Testo\Assert\State;

/**
 * Collector of assertion records.
 */
interface CompositeRecord extends Assertion
{
    /**
     * Get all collected records.
     *
     * @return Assertion[]
     */
    public function getRecords(): array;
}
