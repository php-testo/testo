<?php

declare(strict_types=1);

namespace Testo\Interceptor\TestCaseCallInterceptor;

use Testo\Interceptor\Exception\TestCaseInstantiationException;
use Testo\Interceptor\TestCaseRunInterceptor;
use Testo\Test\Dto\CaseInfo;
use Testo\Test\Dto\CaseResult;
use Testo\Test\TestCaseFactory;

/**
 * Instantiate the test case class if not already instantiated.
 */
final class InstantiateTestCase implements TestCaseRunInterceptor
{
    public function __construct(
        private readonly TestCaseFactory $factory,
    ) {}

    #[\Override]
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        if ($info->instance === null && $info->definition->reflection !== null) {
            try {
                # TODO don't instantiate if the test method is static
                $instance = $this->factory->create($info->definition->reflection);
            } catch (\Throwable $e) {
                throw new TestCaseInstantiationException(previous: $e);
            }

            $info = $info->withInstance($instance);
        }

        return $next($info);
    }
}
