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
 * ## Concurrency: the VCR window is exclusive and non-yielding
 *
 * PHP-VCR is process-global static state — one active cassette, one global hook install, one
 * mode/matcher configuration. It cannot be shared by two tests at once. Testo respects fibers but does
 * not schedule tests concurrently itself; to stay correct if it ever does, this interceptor runs the
 * test **fully contained in its own fiber and drives it to completion without propagating any
 * suspension to the parent scheduler** ({@see self::runToCompletion()}). The `turnOn … turnOff` window
 * therefore never yields control, so no sibling test can touch the global cassette while it is active.
 * A process-wide lock ({@see self::$active}) enforces the invariant and fails loudly if a `#[VCR]`
 * window is ever entered while another is already open.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Vcr
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    testType: TestType::Test,
)]
final class VcrInterceptor implements TestRunInterceptor
{
    /**
     * Guards the process-global php-vcr window: only one `#[VCR]` test may hold the cassette at a time.
     * Structurally, {@see self::runToCompletion()} never yields the window, so under cooperative
     * fibers this can never be contended — it exists to fail loudly if that invariant is ever broken.
     */
    private static bool $active = false;

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $cassette = self::resolveCassette($info);
        if ($cassette === null) {
            return $next($info);
        }

        self::$active and throw new \RuntimeException(
            'A php-vcr cassette is already active in this process. #[VCR] tests run against a single '
            . 'global cassette and cannot overlap; do not trigger one #[VCR] test from within another.',
        );

        $configuration = PhpVcr::configure();
        $cassette->mode === null or $configuration->setMode($cassette->mode->value);
        $cassette->match === [] or $configuration->enableRequestMatchers(
            \array_map(static fn(Matcher $m): string => $m->value, $cassette->match),
        );

        self::$active = true;
        PhpVcr::turnOn();
        PhpVcr::insertCassette($cassette->name);
        try {
            return self::runToCompletion($info, $next);
        } finally {
            PhpVcr::eject();
            PhpVcr::turnOff();
            self::$active = false;
        }
    }

    /**
     * Run the test to completion without letting a suspension escape the VCR window.
     *
     * When there is no surrounding fiber (Testo's current, synchronous mode) the test simply runs
     * inline. When Testo runs the test inside a fiber, we wrap `$next` in a private fiber and resume it
     * ourselves until it terminates instead of re-suspending to the parent scheduler. This keeps the
     * process-global cassette window atomic: it never hands control back mid-test, so a sibling can
     * never observe or clobber the active cassette. A `#[VCR]` test is therefore expected to be
     * synchronous — awaiting real async work inside the window is unsupported by design.
     *
     * @param callable(TestInfo): TestResult $next
     */
    private static function runToCompletion(TestInfo $info, callable $next): TestResult
    {
        if (\Fiber::getCurrent() === null) {
            return $next($info);
        }

        $fiber = new \Fiber(static fn(): TestResult => $next($info));
        $fiber->start();
        while (!$fiber->isTerminated()) {
            $fiber->resume();
        }

        /** @var TestResult $result */
        $result = $fiber->getReturn();
        return $result;
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
