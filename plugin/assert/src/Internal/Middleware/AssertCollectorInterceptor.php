<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Middleware;

use Testo\Assert\AssertPlugin;
use Testo\Assert\Internal\HistoryRenderer;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\TestState;
use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Collects assertions.
 *
 * Creates a new {@see TestState} instance for each test and assigns it to the {@see StaticState}.
 * After the test is executed, the collector is attached to the {@see TestResult} attributes and the
 * complete assertion history is rendered into the {@see AssertPlugin::CHANNEL_HISTORY} channel.
 *
 * The history is emitted here, after {@see $next} returns, because the tree is only final at that
 * point: composite records (e.g. JSON path assertions) are appended to the history empty and filled
 * in afterwards, so streaming per assertion would serialize half-built structures.
 *
 * Supports both synchronous and asynchronous (Fiber-based) environments.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS - 10)]
final readonly class AssertCollectorInterceptor implements TestRunInterceptor
{
    public function __construct(
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $state = new TestState();

        // The collector is installed for the current fiber only, so concurrent tests keep separate
        // histories. StaticState::scope() restores the previous collector on exit — including across a
        // real event-loop suspension inside $next, where the loop resumes this exact fiber directly.
        $result = StaticState::scope($state, static fn(): TestResult => $next($info));

        // Emit after $next returns: the tree is only final here (composite records are appended empty and
        // filled in afterwards, so streaming per assertion would serialize half-built structures).
        $this->messenger->log(
            AssertPlugin::CHANNEL_HISTORY,
            HistoryRenderer::render($state->history),
        );

        return $result
            ->withAttribute(TestState::class, $state)
            ->withSummary($result->summary->withAddedMetric('assertions', \count($state->history)));
    }
}
