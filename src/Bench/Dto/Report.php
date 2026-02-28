<?php

declare(strict_types=1);

namespace Testo\Bench\Dto;

use Testo\Bench\Dto\Report\Severity;

/**
 * Base class for all reports generated during the benchmark execution.
 */
abstract readonly class Report
{
    protected function __construct(
        public Severity $severity,
        public string $reason = '',
        public string $advice = '',
    ) {}
}
