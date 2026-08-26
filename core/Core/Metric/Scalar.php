<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * A dimensionless quantity — a count of iterations or calls, a rank.
 *
 * @api
 */
enum Scalar: string implements Unit
{
    case Number = 'number';

    #[\Override]
    public function suffix(): string
    {
        // A bare count needs no unit to stay legible in a flat namespace.
        return '';
    }
}
