<?php

declare(strict_types=1);

namespace Testo\Bridge\Vcr\Internal;

use Testo\Bridge\Vcr\Matcher;
use Testo\Bridge\VCR;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use VCR\VCR as PhpVcr;

/**
 * Inserts the cassette named by a test's (or its case's) {@see VCR} attribute for the duration of the
 * test, applying that test's record mode and request matchers, then ejects it. Tests without the
 * attribute pass straight through.
 *
 * Ordering ({@see InterceptorOptions::ORDER_CLOSE_TO_TEST}) places this *inside* the retry and repeat
 * interceptors (which sit at low orders, far from the test) and *outside* the lifecycle hooks (which
 * run innermost). So each retry / repeat attempt re-enters here and gets a fresh cassette, and HTTP
 * made from `#[BeforeTest]` / `#[AfterTest]` hooks is covered too.
 *
 * NOTE (concurrency): PHP-VCR is process-global static state — one active cassette per process, one
 * global hook install, and a global mode/matcher configuration. Under Testo's fiber-based concurrency
 * this bridge does NOT yet isolate that: while a cassette is active, a *concurrently scheduled* test's
 * HTTP (even an untagged one) is intercepted against it, and a second `#[VCR]` test would clash. The
 * canonical guard used elsewhere (see {@see \Testo\Assert\Internal\Middleware\AssertCollectorInterceptor})
 * swaps thread-local state on every `Fiber::suspend`/`resume`, but VCR's per-test state is a
 * disk-backed cassette whose playback position would reset on re-insert — so the swap is subtler here.
 * The intended fix (exclusive scheduling of VCR tests, or a validated suspend/resume guard) is tracked
 * in SPEC.md §5.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Vcr
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    testType: TestType::Test,
)]
final readonly class VcrInterceptor implements TestRunInterceptor
{
    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $cassette = self::resolveCassette($info);
        if ($cassette === null) {
            return $next($info);
        }

        $configuration = PhpVcr::configure();
        $cassette->mode === null or $configuration->setMode($cassette->mode->value);
        $cassette->match === [] or $configuration->enableRequestMatchers(
            \array_map(static fn(Matcher $m): string => $m->value, $cassette->match),
        );

        PhpVcr::turnOn();
        PhpVcr::insertCassette($cassette->name);
        try {
            return $next($info);
        } finally {
            PhpVcr::eject();
            PhpVcr::turnOff();
        }
    }

    /**
     * Resolve the effective cassette for the test: the method-level {@see VCR} attribute wins,
     * otherwise the class-level one, otherwise none.
     */
    private static function resolveCassette(TestInfo $info): ?VCR
    {
        $method = $info->testDefinition->reflection->getAttributes(VCR::class);
        if ($method !== []) {
            return $method[0]->newInstance();
        }

        $class = $info->caseInfo->definition->reflection?->getAttributes(VCR::class) ?? [];
        return $class === [] ? null : $class[0]->newInstance();
    }
}
