<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * Outliers ≥ 3 and outliers/total > 10% and |Mean − Median| / Median > 5%
 */
final readonly class BimodalBehavior extends Report
{
    public function __construct(
        public int $outliers,
        public float $outlierRate,
        public float $skew,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Bimodal behavior',
            advice: 'Split into separate benchmarks.',
        );
    }
}
