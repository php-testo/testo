<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * Iter time < 10μs and RStDev✓ < 10%
 */
final readonly class LowIterTime extends Report
{
    public function __construct(
        public float $iterTime,
    ) {
        parent::__construct(
            severity: Severity::Notice,
            reason: 'Low iter time',
            advice: 'Consider increasing calls for better accuracy.',
        );
    }
}
