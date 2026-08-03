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
        try {
            $previous = StaticState::swap($state);

            if (\Fiber::getCurrent() === null) {
                # No Fiber, run the test directly
                $result = $next($info);
            } else {
                # Create a Fiber scope to run the test
                $fiber = new \Fiber(static fn(): TestResult => $next($info));

                $value = $fiber->start();
                while (!$fiber->isTerminated()) {
                    StaticState::swap($previous);
                    try {
                        $resume = \Fiber::suspend($value);
                    } catch (\Throwable $e) {
                        $previous = StaticState::swap($state);
                        $value = $fiber->throw($e);
                        continue;
                    }

                    $previous = StaticState::swap($state);
                    $value = $fiber->resume($resume);
                }

                /** @var TestResult $result */
                $result = $fiber->getReturn();
            }

            $this->messenger->log(
                AssertPlugin::CHANNEL_HISTORY,
                HistoryRenderer::render($state->history),
            );

            return $result
                ->withAttribute(TestState::class, $state)
                ->withSummary($result->summary->withAddedMetric('assertions', \count($state->history)));
        } finally {
            StaticState::swap($previous);
        }
    }
}
