<?php

declare(strict_types=1);

namespace Testo\Application\Internal\Runner;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Event\TestCase\TestCaseFinished;
use Testo\Event\TestCase\TestCasePipelineFinished;
use Testo\Event\TestCase\TestCasePipelineStarting;
use Testo\Event\TestCase\TestCaseStarting;
use Testo\Filter;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Pipeline;

/**
 * @internal
 * @psalm-internal Testo\Application
 */
final readonly class CaseRunner
{
    public function __construct(
        private TestRunner $testRunner,
        private InterceptorProvider $interceptorProvider,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function runCase(CaseInfo $info, Filter $filter): CaseResult
    {
        # TODO handle async tests
        # TODO handle random order

        /**
         * Prepare interceptors pipeline
         *
         * @see TestCaseRunInterceptor::runTestCase()
         * @var list<TestCaseRunInterceptor> $interceptors
         * @var callable(CaseInfo): CaseResult $pipeline
         */
        $interceptors = $this->interceptorProvider->fromConfig(TestCaseRunInterceptor::class);
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: [$info->definition->type]), ...$interceptors)
            ->with(
                $this->run(...),
                'runTestCase',
            );

        $this->eventDispatcher->dispatch(new TestCasePipelineStarting($info));
        /** @var CaseResult $result */
        $result = $pipeline($info);
        $this->eventDispatcher->dispatch(new TestCasePipelineFinished($info, $result));

        return $result;
    }

    public function run(CaseInfo $info): CaseResult
    {
        $this->eventDispatcher->dispatch(new TestCaseStarting($info));

        $results = [];
        $status = Status::Passed;
        foreach ($info->definition->tests->getTests() as $name => $testDefinition) {
            try {
                $testInfo = new TestInfo(
                    name: $name,
                    caseInfo: $info,
                    testDefinition: $testDefinition,
                );

                $result = $this->testRunner->runTest($testInfo);
                # A test aborted by a critical interceptor failure has an unknown verdict — treat it as
                # a case failure so it propagates up to the suite/run status and the process exit code
                # (every reporter already renders an abort as a failure). Deliberately broader than
                # Status::isFailure(), which must keep Aborted out so retry/repeat don't retry an abort.
                ($result->status->isFailure() || $result->status === Status::Aborted)
                    and $status = Status::Failed;

                $results[] = $result;
            } catch (\Throwable $throwable) {
                $status = Status::Error;
                isset($testInfo) and $results[] = new TestResult(
                    info: $testInfo,
                    status: Status::Error,
                    failure: $throwable,
                    summary: Summary::forTest(Status::Error),
                );
            }
        }

        $result = new CaseResult(
            results: $results,
            status: $status,
            summary: Summary::combine(\array_map(
                static fn(TestResult $r): Summary => $r->summary,
                $results,
            )),
        );

        $this->eventDispatcher->dispatch(new TestCaseFinished($info, $result));
        return $result;
    }
}
