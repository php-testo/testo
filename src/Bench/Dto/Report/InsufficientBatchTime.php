<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ > 10% and outliers < 3 and batch time < 10μs
 */
final readonly class InsufficientBatchTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $batchTime,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'Insufficient batch time',
            advice: 'Timer jitter exceeds useful signal — increase invocations per iteration.',
        );
    }
}
