<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ > 20% and batch time < 10μs
 */
final readonly class UnreliableLowBatchTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $batchTime,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Unreliable, low batch time',
            advice: 'Timer overhead dominates — significantly increase invocations per iteration.',
        );
    }
}
