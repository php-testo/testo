<?php

declare(strict_types=1);

namespace Testo\Sample\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Sample\DataProvider;
use Testo\Sample\MultipleResult;
use Testo\Interceptor\TestRunInterceptor;
use Testo\Test\Dto\Status;
use Testo\Test\Dto\TestInfo;
use Testo\Test\Dto\TestResult;
use Testo\Test\Event\Test\TestBatchFinished;
use Testo\Test\Event\Test\TestBatchStarting;
use Testo\Test\Event\Test\TestDataSetFinished;
use Testo\Test\Event\Test\TestDataSetStarting;

/**
 * Interceptor that retries a test execution based on the provided retry policy.
 */
final class DataProviderInterceptor implements TestRunInterceptor
{
    public function __construct(
        private readonly DataProvider $options,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // Dispatch batch starting event
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        // Fetch data sets from the provider
        $dataSets = ($this->options->provider)();

        // Run the test for each data set
        $results = [];
        $status = Status::Passed;
        $num = 0;
        foreach ($dataSets as $k => $dataSet) {
            $label = (string) $k;
            ++$num;
            $i = 0;
            while (\array_key_exists($label, $results)) {
                ++$i;
                $label = "$k~$i";
            }

            $dataSetInfo = $info->with(
                arguments: $dataSet,
            );

            // Dispatch dataset starting event
            $this->eventDispatcher->dispatch(new TestDataSetStarting($dataSetInfo, $label, $num - 1));

            try {
                $result = $next($dataSetInfo);
            } catch (\Throwable $throwable) {
                $result = new TestResult(
                    info: $dataSetInfo,
                    status: Status::Error,
                    failure: $throwable,
                );
            }

            // Dispatch dataset finished event
            $this->eventDispatcher->dispatch(new TestDataSetFinished($dataSetInfo, $result, $label, $num - 1));

            unset($dataSet, $dataSetInfo);
            $result->status->isFailure() and $status = Status::Failed;
            $results[$label] = $result;
        }

        $results = new MultipleResult($results);

        $finalResult = new TestResult(
            info: $info,
            status: $status,
            result: $results,
            attributes: [
                MultipleResult::class => $results,
            ],
        );

        // Dispatch batch finished event
        $this->eventDispatcher->dispatch(new TestBatchFinished($info, $finalResult));

        return $finalResult;
    }
}
