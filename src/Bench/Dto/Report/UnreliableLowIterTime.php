<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ > 20% and iter time < 10μs
 */
final readonly class UnreliableLowIterTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $iterTime,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Unreliable, low iter time',
            advice: 'Timer overhead dominates — significantly increase calls per iteration.',
        );
    }
}
