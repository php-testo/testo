<?php

declare(strict_types=1);

namespace Testo\Metric\Exception;

use Testo\Metric\Unit;

/**
 * Thrown when a value is converted between units of different dimensions — time into bytes.
 *
 * @api
 */
final class IncompatibleUnits extends \InvalidArgumentException
{
    public static function between(Unit $from, Unit $to): self
    {
        return new self(\sprintf(
            'Cannot convert between units of different dimensions: %s::%s and %s::%s.',
            $from::class,
            $from->name,
            $to::class,
            $to->name,
        ));
    }
}
