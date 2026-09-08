<?php

declare(strict_types=1);

namespace Testo\Testing\Internal;

use Internal\Container\Attribute\ScopeShared;
use Internal\Container\Container;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Testing\Attribute\Inject;

/**
 * Wires the test case class instance with its {@see Inject} dependencies.
 *
 * Wraps the case instance provider so that properties marked with {@see Inject} are
 * autowired from the container right after the test case object is created.
 *
 * @internal
 * @psalm-internal Testo
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT)]
#[ScopeShared]
final readonly class InjectInterceptor implements TestCaseRunInterceptor
{
    public function __construct(
        private Container $container,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        # Function/inline tests have no class instance to inject into.
        if ($info->instance === null) {
            return $next($info);
        }

        return $next($info->withInstance(
            new InjectingCaseInstance($info->instance, new PropertyInjector($this->container)),
        ));
    }
}
