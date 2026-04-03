<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Internal\Destroy\Destroyable;
use Testo\Codecov\Dto\CoverageResult;
use Testo\Codecov\Report\CoverageReport;
use Testo\Core\Context\SuiteResult;

/**
 * Mutable aggregate that collects coverage data across test suites.
 *
 * Merges per-test coverage from suite results as they finish.
 * On destruction, generates all configured reports.
 *
 * @internal
 */
final class CoverageAggregate implements Destroyable
{
    private CoverageResult $result;

    /**
     * @param list<CoverageReport> $reports
     */
    public function __construct(
        private readonly array $reports,
    ) {
        $this->result = new CoverageResult();
    }

    public function mergeSuiteResult(SuiteResult $suiteResult): void
    {
        foreach ($suiteResult as $caseResult) {
            foreach ($caseResult as $testResult) {
                $coverage = $testResult->getAttribute(CoverageResult::class);
                $coverage instanceof CoverageResult and $this->result = $this->result->merge($coverage);
            }
        }
    }

    public function destroy(): void
    {
        foreach ($this->reports as $report) {
            $report->generate($this->result);
        }
    }
}
