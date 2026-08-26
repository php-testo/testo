<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * The unit a {@see Metric} was measured in.
 *
 * The parent of every dimension enum ({@see Time}, {@see Memory}, {@see Percent}, {@see Scalar}), so a
 * metric can name a unit from any family while a reporter still handles all of them through one type.
 * Each family is its own enum, and its cases scale within it; converting between families is a reporter's
 * job at its own boundary.
 *
 * @api
 */
interface Unit
{
    /**
     * Suffix a flat-namespace consumer appends to a metric name so the unit survives where there is no
     * field to carry it — a JUnit `<property>` name becomes `…mean.us`, `…memory.bytes`. A dimensionless
     * number adds nothing.
     */
    public function suffix(): string;
}
