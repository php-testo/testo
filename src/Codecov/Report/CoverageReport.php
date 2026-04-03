<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Testo\Codecov\Dto\CoverageResult;

/**
 * Generates a coverage report in a specific format.
 *
 * @api
 */
interface CoverageReport
{
    public function generate(CoverageResult $result): void;
}
