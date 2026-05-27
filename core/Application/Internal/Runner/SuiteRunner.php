<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Runner;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\SimpleCaseInstantiator;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Internal\DefaultTestHandler;
use Testo\Core\Value\Status;
use Testo\Event\TestSuite\TestSuiteFinished;
use Testo\Event\TestSuite\TestSuitePipelineFinished;
use Testo\Event\TestSuite\TestSuitePipelineStarting;
use Testo\Event\TestSuite\TestSuiteStarting;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\TestSuiteRunInterceptor;
use Testo\Pipeline\Pipeline;

/**
 * A test suite runner that executes a suite of tests and returns the results.
 *
 * @internal
 * @psalm-internal Testo\Application
 */
final readonly class SuiteRunner
{
    public function __construct(
        private CaseRunner $caseRunner,
        private InterceptorProvider $interceptorProvider,
        private EventDispatcherInterface $eventDispatcher,
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
        $pipeline = Pipeline::prepare($filter->type, ...$interceptors)
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

        // Todo: unhardcode
        $handler = (new DefaultTestHandler())(...);

        # Run tests for each case
        foreach ($suite->testCases->getCases() as $caseDefinition) {
            try {
                $caseInfo = new CaseInfo(
                    definition: $caseDefinition,
                    instance: $caseDefinition->reflection === null
                        ? null
                        : new SimpleCaseInstantiator($caseDefinition->reflection),
                    handler: $caseDefinition->handler ?? $handler,
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
