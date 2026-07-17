<?php

declare(strict_types=1);

namespace Testo\Async\Internal;

use Testo\Async\Strategy;
use Testo\Concurrent;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Wires a {@see Concurrent} test case to a scheduling {@see Strategy}.
 *
 * v1 implements {@see Strategy::Sequential} as a pass-through: the case runs one test after another
 * (Testo's default order), and any test individually marked {@see \Testo\Async} still gets its own
 * coroutine via {@see AsyncRunInterceptor}. A single shared loop run for the whole case is deliberately
 * NOT taken yet: it would place the case inside one loop fiber, forcing Testo's fiber-aware scoped
 * state guards (assertion collector, messenger scope) onto their nested-fiber path, which deadlocks
 * against Revolt resuming those same fibers. Making the guards Revolt-compatible is the prerequisite
 * for the shared-loop interleaving strategies.
 *
 * Interleaving strategies (RoundRobin/Random) need that cooperative scheduler and are not implemented,
 * so requesting one fails loudly rather than silently degrading to sequential.
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
        $this->options->strategy === Strategy::Sequential
            or throw new \LogicException(\sprintf(
                'Concurrent strategy "%s" is not implemented yet.',
                $this->options->strategy->name,
            ));

        return $next($info);
    }
}
