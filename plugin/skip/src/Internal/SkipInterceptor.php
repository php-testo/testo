<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

use Testo\Common\Reflection;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Skip;

/**
 * Reports a {@see Skip}-marked test as skipped, with the attribute's reason, instead of running it.
 *
 * The attribute spawns one instance per `#[Skip]` it is found on, class and function alike, and
 * {@see ConflictPolicy::First} collapses them — so which instance survives says nothing about which
 * reason applies, and the reason is read back from reflection instead: nearest declaration first,
 * the function's own attribute over an inherited one, the function over the class.
 *
 * Ordering: inner to the filter, outer to every interceptor that prepares, wraps or multiplies a
 * test body, since none of them has anything to do for a test that has no body to run.
 *
 * Returns the result instead of throwing {@see SkipTest}: a throw leaves the pipeline and lands as
 * {@see Status::Aborted}.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_FILTER + 1_000,
    onConflict: ConflictPolicy::First,
    testType: TestType::Test,
)]
final readonly class SkipInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        return new TestResult(
            info: $info,
            status: Status::Skipped,
            failure: new SkipTest(self::reason($info)),
            attributes: ['duration' => 0, 'description' => $info->testDefinition->getDescription()],
            summary: Summary::forTest(Status::Skipped),
        );
    }

    /**
     * `{testId} is skipped via #[Skip]`, extended with ` ==> {reason}` when a reason is given.
     * The test id is the string `--filter` takes back.
     */
    private static function reason(TestInfo $info): string
    {
        $message = "{$info->identity->fqn()} is skipped via #[Skip]";
        $reason = self::attribute($info)?->reason ?? '';

        return $reason === '' ? $message : "{$message} ==> {$reason}";
    }

    /**
     * The attribute whose reason applies to this test: `limit: 1` stops at the nearest declaration,
     * so an overriding function's own `#[Skip]` is taken over the one it inherits. The class is
     * consulted only when the function carries none of its own.
     */
    private static function attribute(TestInfo $info): ?Skip
    {
        $attributes = Reflection::fetchFunctionAttributes(
            $info->testDefinition->reflection,
            attributeClass: Skip::class,
            limit: 1,
        );

        $class = $info->caseInfo->definition->reflection;
        $attributes === [] && $class !== null and $attributes = Reflection::fetchClassAttributes(
            $class,
            attributeClass: Skip::class,
            limit: 1,
        );

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
