<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * A duration, in one of the units it may be measured in.
 *
 * @api
 */
enum Time: string implements Unit
{
    case Nanoseconds = 'ns';
    case Microseconds = 'us';
    case Milliseconds = 'ms';
    case Seconds = 's';

    #[\Override]
    public function suffix(): string
    {
        return '.' . $this->value;
    }
}
