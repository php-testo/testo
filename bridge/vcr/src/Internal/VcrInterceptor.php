<?php

declare(strict_types=1);

namespace Testo\Bridge\VCR\Internal;

use Testo\Bridge\VCR;
use Testo\Bridge\VCR\Matcher;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use VCR\VCR as PhpVcr;

/**
 * Handles the {@see VCR} attribute: inserts the named cassette for the duration of the test, applying
 * the attribute's record mode and request matchers, then ejects it.
 *
 * Wired in automatically by the {@see VCR} attribute (it is `Interceptable` with a
 * {@see \Testo\Pipeline\Attribute\FallbackInterceptor}), so this only ever runs for tagged tests and
 * receives the resolved {@see VCR} instance via the constructor — no per-test reflection. When a test
 * carries both a class-level and a method-level `#[VCR]`, {@see ConflictPolicy::Last} keeps the
 * method's (class = default, method overrides).
 *
 * Ordering ({@see InterceptorOptions::ORDER_CLOSE_TO_TEST}) places this *inside* the retry and repeat
 * interceptors (which sit far from the test) and *outside* the lifecycle hooks, so each retry/repeat
 * attempt re-enters here with a fresh cassette and HTTP from `#[BeforeTest]`/`#[AfterTest]` is covered.
 *
 * ## Concurrency: the VCR window is exclusive and non-yielding
 *
 * PHP-VCR is process-global static state — one active cassette, one global hook install, one
 * mode/matcher configuration — so it cannot be shared by two tests at once. Testo respects fibers but
 * does not schedule tests concurrently itself; to stay correct if it ever does, the test runs **fully
 * contained in its own fiber, driven to completion without propagating any suspension to the parent
 * scheduler** ({@see self::runToCompletion()}). The `turnOn … turnOff` window therefore never yields,
 * so no sibling test can touch the global cassette while it is active. {@see self::$active} enforces
 * the invariant and fails loudly if a `#[VCR]` window is entered while another is already open.
 *
 * @internal
 * @psalm-internal Testo\Bridge\VCR
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_CLOSE_TO_TEST,
    onConflict: ConflictPolicy::Last,
)]
final class VcrInterceptor implements TestRunInterceptor
{
    /**
     * Guards the process-global php-vcr window: only one `#[VCR]` test may hold the cassette at a time.
     * Structurally, {@see self::runToCompletion()} never yields the window, so under cooperative
     * fibers this can never be contended — it exists to fail loudly if that invariant is ever broken.
     */
    private static bool $active = false;

    public function __construct(
        private readonly VCR $options,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        self::$active and throw new \RuntimeException(
            'A php-vcr cassette is already active in this process. #[VCR] tests run against a single '
            . 'global cassette and cannot overlap; do not trigger one #[VCR] test from within another.',
        );

        $configuration = PhpVcr::configure();
        $configuration->enableLibraryHooks(self::libraryHooks());
        $this->options->mode === null or $configuration->setMode($this->options->mode->value);
        $this->options->match === [] or $configuration->enableRequestMatchers(
            \array_map(static fn(Matcher $m): string => $m->value, $this->options->match),
        );

        self::$active = true;
        try {
            PhpVcr::turnOn();
            PhpVcr::insertCassette($this->options->name);
            return self::runToCompletion($info, $next);
        } finally {
            # turnOff() ejects the cassette itself and is a no-op when VCR never came on, so a failure
            # inside turnOn() unwinds with its own cause. The guard is released first: a throw here
            # must not leave every later #[VCR] test refusing to start.
            self::$active = false;
            PhpVcr::turnOff();
        }
    }

    /**
     * Library hooks php-vcr installs when the window opens.
     *
     * Left alone, php-vcr enables every hook it knows — including the SOAP one, whose constructor
     * hard-requires `ext-soap` and throws from inside `VCR::turnOn()` when it is missing. The extension
     * is declared `require-dev` by php-vcr itself, so Composer neither installs it for a consumer nor
     * warns about it: every `#[VCR]` test on a soap-less build dies, whether or not it speaks SOAP.
     *
     * Hence the set is stated explicitly, with `soap` added only when it can actually be constructed —
     * the same pair of classes `\VCR\LibraryHooks\SoapHook` checks. HTTP recording keeps working
     * everywhere; SOAP recording stays available wherever `ext-soap` is installed.
     *
     * @return list<non-empty-string>
     */
    private static function libraryHooks(): array
    {
        $hooks = ['stream_wrapper', 'curl'];

        \class_exists(\SoapClient::class) && \class_exists(\DOMDocument::class) and $hooks[] = 'soap';

        return $hooks;
    }

    /**
     * Run the test to completion without letting a suspension escape the VCR window.
     *
     * With no surrounding fiber the test runs inline. When Testo runs the test inside a fiber, `$next`
     * is wrapped in a private fiber and resumed here until it terminates instead of re-suspending to the
     * parent scheduler — keeping the process-global cassette window atomic. A `#[VCR]` test is therefore
     * expected to be synchronous; awaiting real async work inside the window is unsupported by design.
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
}
