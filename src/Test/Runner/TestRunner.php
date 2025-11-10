<?php

declare(strict_types=1);

namespace Testo\Test\Runner;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Interceptor\Exception\PipelineFailure;
use Testo\Interceptor\TestRunInterceptor;
use Testo\Module\Interceptor\InterceptorProvider;
use Testo\Module\Interceptor\Internal\Pipeline;
use Testo\Test\Dto\Status;
use Testo\Test\Dto\TestInfo;
use Testo\Test\Dto\TestResult;
use Testo\Test\Event\Test\TestFinished;
use Testo\Test\Event\Test\TestPipelineFinished;
use Testo\Test\Event\Test\TestPipelineStarting;
use Testo\Test\Event\Test\TestStarting;

final class TestRunner
{
    public function __construct(
        private readonly InterceptorProvider $interceptorProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function runTest(TestInfo $info): TestResult
    {
        $this->eventDispatcher->dispatch(new TestPipelineStarting($info));

        try {
            # Build interceptors pipeline
            $interceptors = $this->interceptorProvider->fromConfig(TestRunInterceptor::class);

            $result = Pipeline::prepare(...$interceptors)->with(
                function (TestInfo $info): TestResult {
                    $this->eventDispatcher->dispatch(new TestStarting($info));

                    # TODO don't instantiate if the method is static
                    $instance = $info->caseInfo->instance;
                    try {
                        $startTime = \microtime(true);
                        $executionResult = $instance === null
                            ? $info->testDefinition->reflection->invoke(...$info->arguments)
                            : $info->testDefinition->reflection->invoke($instance, ...$info->arguments);
                        $duration = \microtime(true) - $startTime;

                        $result = new TestResult(
                            info: $info,
                            status: Status::Passed,
                            result: $executionResult,
                            attributes: [
                                'duration' => (int) \round($duration * 1000),
                            ],
                        );
                    } catch (\Throwable $throwable) {
                        $duration = \microtime(true) - $startTime;

                        $result = new TestResult(
                            info: $info,
                            status: Status::Error,
                            failure: $throwable,
                            attributes: [
                                'duration' => (int) \round($duration * 1000),
                            ],
                        );
                    }

                    $this->eventDispatcher->dispatch(new TestFinished($info, $result));
                    return $result;
                },
                /** @see TestRunInterceptor::runTest() */
                'runTest',
            )($info);
        } catch (\Throwable $e) {
            $result = new TestResult(
                info: $info,
                status: Status::Aborted,
                failure: new PipelineFailure('Error during test execution pipeline.', previous: $e),
            );
        }

        $this->eventDispatcher->dispatch(new TestPipelineFinished($info, $result));

        return $result;
    }
}
