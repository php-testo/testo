<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ 10-20% and iter time < 10μs
 */
final readonly class HighVarianceLowIterTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $iterTime,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'High variance, low iter time',
            advice: 'Measurement overhead may dominate — increase calls per iteration.',
        );
    }
}
