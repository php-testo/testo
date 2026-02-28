<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * Batch time < 10μs and RStDev✓ ≤ 10%
 */
final readonly class LowBatchTime extends Report
{
    public function __construct(
        public float $batchTime,
    ) {
        parent::__construct(
            severity: Severity::Notice,
            reason: 'Low batch time',
            advice: 'Consider increasing invocations for better accuracy.',
        );
    }
}
