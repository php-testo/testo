<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Internal\Destroy\Destroyable;
use Testo\Codecov\Result\CoverageResult;
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
final readonly class CoverageCollector implements Destroyable
{
    private Cache $cache;

    /**
     * @param list<CoverageReport> $reports
     */
    public function __construct(
        private array $reports,
    ) {
        $this->cache = new Cache(new CoverageResult());
    }

    public function mergeSuiteResult(SuiteResult $suiteResult): void
    {
        $r = $this->cache->value;
        foreach ($suiteResult as $caseResult) {
            foreach ($caseResult as $testResult) {
                $coverage = $testResult->getAttribute(CoverageResult::class);
                $coverage instanceof CoverageResult and $r = $r->merge($coverage);
            }
        }

        $this->cache->value = $r;
    }

    public function destroy(): void
    {
        foreach ($this->reports as $report) {
            $report->generate($this->cache->value);
        }
    }
}
