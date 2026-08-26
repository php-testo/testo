<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * A ratio already expressed out of 100.
 *
 * @api
 */
enum Percent: string implements Unit
{
    case Percent = 'percent';

    #[\Override]
    public function suffix(): string
    {
        return '.' . $this->value;
    }
}
