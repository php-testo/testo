<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Internal\Destroy\Destroyable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Report\CoverageReport;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestResult;
use Testo\Data\MultipleResult;
use Testo\Event\Report\ReportFileGenerated;

/**
 * Mutable aggregate that collects coverage data across test suites.
 *
 * Merges per-test coverage from suite results as they finish.
 * On destruction, generates all configured reports and announces each written file.
 *
 * @internal
 */
final readonly class CoverageCollector implements Destroyable
{
    private Cache $cache;

    /**
     * @param list<CoverageReport> $reports
     * @param EventDispatcherInterface|null $dispatcher Null keeps written reports unannounced.
     */
    public function __construct(
        private array $reports,
        private ?string $sourceRoot = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->cache = new Cache(new CoverageResult());
    }

    public function mergeSuiteResult(SuiteResult $suiteResult): void
    {
        $r = $this->cache->value;
        foreach ($suiteResult as $caseResult) {
            foreach ($caseResult as $testResult) {
                foreach (self::collectCoverage($testResult) as $coverage) {
                    $r = $r->merge($coverage);
                }
            }
        }

        $this->cache->value = $r;
    }

    /**
     * Yields coverage from a test result and, for aggregate results (data providers,
     * inline tests), from every nested per-run result.
     *
     * Coverage is collected by the inner {@see \Testo\Codecov\Internal\Middleware\CoverageTestInterceptor},
     * so for data-driven tests it lives on the per-data-set child results rather than on the
     * {@see MultipleResult} wrapper returned for the test as a whole.
     *
     * @return iterable<CoverageResult>
     */
    private static function collectCoverage(TestResult $testResult): iterable
    {
        $coverage = $testResult->getAttribute(CoverageResult::class);
        $coverage instanceof CoverageResult and yield $coverage;

        $multiple = $testResult->getAttribute(MultipleResult::class);
        if ($multiple instanceof MultipleResult) {
            foreach ($multiple->results as $nested) {
                yield from self::collectCoverage($nested);
            }
        }
    }

    public function destroy(): void
    {
        $result = $this->sourceRoot !== null
            ? $this->cache->value->withSourceRoot($this->sourceRoot)
            : $this->cache->value;

        foreach ($this->reports as $report) {
            $report->generate($result);
            $this->dispatcher?->dispatch(new ReportFileGenerated($report->info()));
        }
    }
}
