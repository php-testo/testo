<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

use Testo\Common\Reflection;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Flags the tests skipped by `#[Skip]` or an unmet `#[Requires]` as
 * {@see \Testo\Core\Definition\TestDefinition::$skipped} once the cases of a file are located.
 *
 * The flag alone: {@see SkipInterceptor} takes the message from the decision {@see SkipDecision}
 * keeps.
 *
 * A class-level attribute is inherited from parents and traits, a method-level one from the
 * overridden method — {@see Reflection} walks both chains by default.
 *
 * Ordering: outer to the filter, so its deactivations are in place by the time this interceptor
 * looks at the cases. A deactivated test is left alone: it never runs, and checking its
 * requirements would only probe the environment for nothing.
 *
 * `testType: TestType::Test` only drops this interceptor from a run filtered to other types; a
 * located file still yields cases of every type, hence the per-case check that keeps both
 * attributes inert outside a plain test.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_FILTER - 1_000, testType: TestType::Test)]
final readonly class SkipLocatorInterceptor implements CaseLocatorInterceptor
{
    #[\Override]
    public function locateTestCases(FileDefinitions $file, callable $next): CaseDefinitions
    {
        /** @var CaseDefinitions $result */
        $result = $next($file);

        foreach ($result->getCases() as $case) {
            if ($case->type !== TestType::Test->value) {
                continue;
            }

            foreach ($case->tests->getTests() as $test) {
                try {
                    SkipDecision::of($test, $case->reflection) === null or $test->skipped = true;
                } catch (\Throwable) {
                    # The body is not going to run either way: the decision keeps the throw and
                    # rethrows it in the test's own pipeline, which reports it against that test
                    # instead of failing the file. Flagged, so the case's hooks stay silent for it.
                    $test->skipped = true;
                }
            }
        }

        return $result;
    }
}
