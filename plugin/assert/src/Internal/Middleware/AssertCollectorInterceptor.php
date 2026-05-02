<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Middleware;

use Testo\Assert\Internal\StaticState;
use Testo\Assert\TestState;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Collects assertions.
 *
 * Creates a new {@see TestState} instance for each test and assigns it to the {@see StaticState}.
 * After the test is executed, the collector is attached to the {@see TestResult} attributes.
 *
 * Supports both synchronous and asynchronous (Fiber-based) environments.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_ASSERTIONS - 10)]
final readonly class AssertCollectorInterceptor implements TestRunInterceptor
{
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

            return $result->withAttribute(TestState::class, $state);
        } finally {
            StaticState::swap($previous);
        }
    }
}
