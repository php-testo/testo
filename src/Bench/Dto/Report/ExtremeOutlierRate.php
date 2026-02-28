<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * Outliers ≥ 3 and outliers/total > 20%
 */
final readonly class ExtremeOutlierRate extends Report
{
    public function __construct(
        public int $outliers,
        public float $outlierRate,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Extreme outlier rate',
            advice: 'Results invalid — split benchmarks or fix environment.',
        );
    }
}
