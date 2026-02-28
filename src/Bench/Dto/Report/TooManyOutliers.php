<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * Outliers ≥ 3 and outliers/total 10-20%
 */
final readonly class TooManyOutliers extends Report
{
    public function __construct(
        public int $outliers,
        public float $outlierRate,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'Too many outliers',
            advice: 'Check for cache/GC effects.',
        );
    }
}
