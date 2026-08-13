<?php

declare(strict_types=1);

namespace Testo\Codecov\Report;

use Testo\Codecov\Result\CoverageResult;
use Testo\Core\Report\ReportInfo;

/**
 * Generates a coverage report in a specific format.
 *
 * @api
 */
interface CoverageReport
{
    public function generate(CoverageResult $result): void;

    /**
     * The file this report writes, for the run to announce.
     */
    public function info(): ReportInfo;
}
