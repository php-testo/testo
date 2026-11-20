<?php

declare(strict_types=1);

namespace Testo\Testing\Internal;

use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Testing\Attribute\ExpectAssertionsCount;
use Testo\Testing\Attribute\ExpectTestResultAttribute;
use Testo\Testing\Attribute\ExpectTestStatus;

/**
 * Validates {@see ExpectTestStatus}, {@see ExpectAssertionsCount}, and
 * {@see ExpectTestResultAttribute} declarations against the {@see TestResult} returned by
 * the test method (typically from {@see \Testo\Testing\Helper\TestRunner::runTest()}).
 *
 * Passes through immediately when none of the expect attributes are present on the method.
 * If the outer test already failed before returning a result, the failure is preserved
 * without additional validation so the original error is not obscured.
 *
 * @internal
 * @psalm-internal Testo
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_CLOSE_TO_TEST - 1)]
final readonly class ExpectInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $reflection = $info->testDefinition->reflection;

        $statusAttrs = Reflection::fetchFunctionAttributes($reflection, attributeClass: ExpectTestStatus::class);
        $countAttrs = Reflection::fetchFunctionAttributes($reflection, attributeClass: ExpectAssertionsCount::class);
        $attrAttrs = Reflection::fetchFunctionAttributes($reflection, attributeClass: ExpectTestResultAttribute::class);

        if ($statusAttrs === [] && $countAttrs === [] && $attrAttrs === []) {
            return $next($info);
        }

        $outerResult = $next($info);

        // Pre-existing failure or non-terminal status — preserve as-is so the original error is
        // not obscured by a misleading "expected TestResult" message.
        if (!$outerResult->status->isCompleted() || $outerResult->status->isFailure()) {
            return $outerResult;
        }

        $stubResult = $outerResult->result;
        if (!$stubResult instanceof TestResult) {
            return $outerResult
                ->with(status: Status::Failed)
                ->withFailure(new \LogicException(
                    'Test must return the TestResult from TestRunner::runTest() when using Expect* attributes, got '
                    . \get_debug_type($stubResult),
                ));
        }

        $failures = [];

        if ($statusAttrs !== []) {
            /** @var ExpectTestStatus $expect */
            $expect = $statusAttrs[0]->newInstance();
            if ($stubResult->status !== $expect->status) {
                $failures[] = "Expected stub status {$expect->status->name}, got {$stubResult->status->name}";
            }
        }

        if ($countAttrs !== []) {
            /** @var ExpectAssertionsCount $expect */
            $expect = $countAttrs[0]->newInstance();
            $actual = $stubResult->summary->metric('assertions');
            if ($actual !== $expect->count) {
                $failures[] = "Expected {$expect->count} assertion(s), got {$actual}";
            }
        }

        foreach ($attrAttrs as $attr) {
            /** @var ExpectTestResultAttribute $expect */
            $expect = $attr->newInstance();
            if ($stubResult->getAttribute($expect->name) === null) {
                $failures[] = "Expected TestResult attribute '{$expect->name}' to be present";
            }
        }

        if ($failures === []) {
            return $outerResult;
        }

        return $outerResult
            ->with(status: Status::Failed)
            ->withFailure(new \RuntimeException(\implode("\n", $failures)));
    }
}
