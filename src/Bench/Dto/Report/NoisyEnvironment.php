<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev > 10% and outliers < 3
 */
final readonly class NoisyEnvironment extends Report
{
    public function __construct(
        public float $RStDev,
        public int $outliers,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'Noisy environment',
            advice: 'Close background processes or pin CPU.',
        );
    }
}
