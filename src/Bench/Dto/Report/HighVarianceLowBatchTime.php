<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev✓ 10-20% and batch time < 10μs
 */
final readonly class HighVarianceLowBatchTime extends Report
{
    public function __construct(
        public float $RStDev,
        public float $batchTime,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'High variance, low batch time',
            advice: 'Measurement overhead may dominate — increase invocations per iteration.',
        );
    }
}
