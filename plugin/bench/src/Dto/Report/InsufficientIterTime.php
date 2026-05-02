<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ > 10% and outliers < 3 and iter time < 10μs
 */
final readonly class InsufficientIterTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $iterTime,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'Insufficient iter time',
            advice: 'Timer jitter exceeds useful signal — increase calls per iteration.',
        );
    }
}
