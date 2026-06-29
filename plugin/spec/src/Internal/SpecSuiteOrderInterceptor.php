<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Pipeline\Middleware\TestSuiteRunInterceptor;

/**
 * Reorders a suite's Test Cases by their spec section number before the suite runs, so tests execute
 * in the order the specification document presents them.
 *
 * Sorting happens in place on the located {@see \Testo\Core\Definition\CaseDefinitions}; the same
 * comparator drives {@see SpecCollector} so the run order and the generated document agree. Cases
 * without a section number keep their relative order and fall to the end.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final readonly class SpecSuiteOrderInterceptor implements TestSuiteRunInterceptor
{
    #[\Override]
    public function runTestSuite(SuiteInfo $info, callable $next): SuiteResult
    {
        $info->testCases->sort(static fn(CaseDefinition $a, CaseDefinition $b): int => SpecNumberer::compareSections(
            SpecHeaderReader::section($a->reflection)?->number,
            SpecHeaderReader::section($b->reflection)?->number,
        ));

        return $next($info);
    }
}
