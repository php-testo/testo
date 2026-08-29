<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * |Mean − Median| / Median > 10%
 */
final readonly class HeavilySkewed extends Report
{
    public function __construct(
        public float $skew,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Heavily skewed',
            advice: 'Split into separate benchmarks.',
        );
    }
}
