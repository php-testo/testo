<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * |Mean − Median| / Median 5-10%
 */
final readonly class SkewedDistribution extends Report
{
    public function __construct(
        public float $skew,
    ) {
        parent::__construct(
            severity: Severity::Warning,
            reason: 'Skewed distribution',
            advice: 'Increase iterations or review function behavior.',
        );
    }
}
