<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Runner;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Exception\PipelineFailure;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Exception\CancelTest;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Event\Test\TestFinished;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Event\Test\TestStarting;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Pipeline;

/**
 * @internal
 * @psalm-internal Testo\Application
 */
final readonly class TestRunner
{
    public function __construct(
        private InterceptorProvider $interceptorProvider,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function runTest(TestInfo $info): TestResult
    {
        $this->eventDispatcher->dispatch(new TestPipelineStarting($info));
        $description = $info->testDefinition->getDescription();

        try {
            # Build interceptors pipeline
            $interceptors = $this->interceptorProvider->fromConfig(TestRunInterceptor::class);

            $result = Pipeline::prepare($info->caseInfo->definition->type, ...$interceptors)->with(
                function (TestInfo $info) use ($description): TestResult {
                    $this->eventDispatcher->dispatch(new TestStarting($info));

                    try {
                        $startTime = \microtime(true);
                        $executionResult = ($info->caseInfo->handler)($info);
                        $duration = \microtime(true) - $startTime;

                        $result = new TestResult(
                            info: $info,
                            status: Status::Passed,
                            result: $executionResult,
                            attributes: [
                                'duration' => (int) \round($duration * 1000),
                                'description' => $description,
                            ],
                        );
                    } catch (\Throwable $throwable) {
                        $duration = \microtime(true) - $startTime;

                        $status = match (true) {
                            $throwable instanceof SkipTest => Status::Skipped,
                            $throwable instanceof CancelTest => Status::Cancelled,
                            default => Status::Error,
                        };

                        $result = new TestResult(
                            info: $info,
                            status: $status,
                            failure: $throwable,
                            attributes: [
                                'duration' => (int) \round($duration * 1000),
                                'description' => $description,
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
                attributes: [
                    'description' => $description,
                ],
            );
        } finally {
            $this->eventDispatcher->dispatch(new TestPipelineFinished($info, $result));
        }

        return $result;
    }
}
