<?php

declare(strict_types=1);

namespace Testo\Bench\Dto\Report;

use Testo\Bench\Dto\Report;

/**
 * RStDev >20%
 */
final readonly class VeryHighVariance extends Report
{
    public function __construct(
        public float $RStDev,
    ) {
        parent::__construct(
            severity: Severity::Danger,
            reason: 'Very high variance',
            advice: 'Results unreliable — environment too noisy or function is non-deterministic.',
        );
    }
}
