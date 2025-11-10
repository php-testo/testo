<?php

declare(strict_types=1);

namespace Testo\Test\Runner;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Common\Filter;
use Testo\Interceptor\TestSuiteRunInterceptor;
use Testo\Module\Interceptor\InterceptorProvider;
use Testo\Module\Interceptor\Internal\Pipeline;
use Testo\Test\Dto\CaseInfo;
use Testo\Test\Dto\Status;
use Testo\Test\Dto\SuiteInfo;
use Testo\Test\Dto\SuiteResult;
use Testo\Test\Event\TestSuite\TestSuiteFinished;
use Testo\Test\Event\TestSuite\TestSuitePipelineFinished;
use Testo\Test\Event\TestSuite\TestSuitePipelineStarting;
use Testo\Test\Event\TestSuite\TestSuiteStarting;

/**
 * A test suite runner that executes a suite of tests and returns the results.
 */
final class SuiteRunner
{
    public function __construct(
        private readonly CaseRunner $caseRunner,
        private readonly InterceptorProvider $interceptorProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function runSuite(SuiteInfo $info, Filter $filter): SuiteResult
    {
        /**
         * Prepare interceptors pipeline
         *
         * @see TestSuiteRunInterceptor::runTestSuite()
         * @var list<TestSuiteRunInterceptor> $interceptors
         * @var callable(SuiteInfo): SuiteResult $pipeline
         */
        $interceptors = $this->interceptorProvider->fromConfig(TestSuiteRunInterceptor::class);
        $pipeline = Pipeline::prepare(...$interceptors)
            ->with(
                fn(SuiteInfo $info): SuiteResult => $this->run($info, $filter),
                'runTestSuite',
            );

        $this->eventDispatcher->dispatch(new TestSuitePipelineStarting($info));
        $result = $pipeline($info);
        $this->eventDispatcher->dispatch(new TestSuitePipelineFinished($info, $result));

        return $result;
    }

    public function run(SuiteInfo $suite, Filter $filter): SuiteResult
    {
        $this->eventDispatcher->dispatch(new TestSuiteStarting($suite));

        # Apply suite name filter if exists
        $suite->name === null or $filter = $filter->with(testSuites: [$suite->name]);

        // todo if random, run in random order?

        $runner = $this->caseRunner;
        $results = [];
        $status = Status::Passed;
        # Run tests for each case
        foreach ($suite->testCases->getCases() as $caseDefinition) {
            try {
                $caseInfo = new CaseInfo(
                    definition: $caseDefinition,
                );
                $result = $runner->runCase($caseInfo, $filter);
                $result->status->isFailure() and $status = Status::Failed;
                $results[] = $result;
            } catch (\Throwable) {
                // Skip for now
                $status = Status::Error;
            }
        }

        $result = new SuiteResult($results, status: $status);

        $this->eventDispatcher->dispatch(new TestSuiteFinished($suite, $result));
        return $result;
    }
}
