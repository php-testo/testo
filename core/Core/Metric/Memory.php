<?php

declare(strict_types=1);

namespace Testo\Core\Metric;

/**
 * An amount of memory, in one of the units it may be measured in.
 *
 * @api
 */
enum Memory: string implements Unit
{
    case Bytes = 'bytes';
    case Kilobytes = 'kb';
    case Megabytes = 'mb';
    case Gigabytes = 'gb';

    #[\Override]
    public function suffix(): string
    {
        return '.' . $this->value;
    }
}
