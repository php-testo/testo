<?php

declare(strict_types=1);

namespace Testo\Async\Internal;

use Revolt\EventLoop;
use Testo\Async\Strategy;
use Testo\Concurrent;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Runs a whole {@see Concurrent} test case within one shared Revolt event-loop run.
 *
 * v1 implements {@see Strategy::Sequential}: the case's normal sequential run is wrapped in a single
 * loop drive, so every test shares one loop lifetime and may await while still executing one after
 * another. Interleaving strategies (RoundRobin/Random) need a cooperative scheduler that replaces the
 * sequential run and re-emits the case events — not yet implemented, so requesting one fails loudly.
 *
 * @internal
 * @psalm-internal Testo\Async
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT, onConflict: ConflictPolicy::Last)]
final readonly class ConcurrentRunInterceptor implements TestCaseRunInterceptor
{
    public function __construct(
        private Concurrent $options,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $this->options->strategy === Strategy::Sequential or throw new \LogicException(
            \sprintf('Concurrent strategy "%s" is not implemented yet.', $this->options->strategy->name),
        );

        $suspension = EventLoop::getSuspension();

        EventLoop::queue(static function () use ($suspension, $next, $info): void {
            try {
                $suspension->resume($next($info));
            } catch (\Throwable $e) {
                $suspension->throw($e);
            }
        });

        /** @var CaseResult */
        return $suspension->suspend();
    }
}
