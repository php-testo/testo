<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev 10-20%
 */
final readonly class HighVariance extends Report
{
    public function __construct(
        public float $RStDev,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'High variance',
            advice: 'Increase iterations or isolate side effects.',
        );
    }
}
