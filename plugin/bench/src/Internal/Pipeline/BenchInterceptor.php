<?php

declare(strict_types=1);

namespace Testo\Bench\Internal\Pipeline;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Bench;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Core\Value\TestType;
use Testo\Data\MultipleResult;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Filter\DataPointer;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Handles {@see \Testo\Bench} attribute by running
 *
 * @internal
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_DATA_PROVIDER,
    onConflict: ConflictPolicy::First,
    testType: TestType::BenchInline,
)]
final readonly class BenchInterceptor implements TestRunInterceptor
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        /** @var \Testo\Bench[] $attributes */
        $attributes = $info->getAttribute(Bench::class);
        if ($attributes === []) {
            return $next($info);
        }

        if (\count($attributes) === 1) {
            $attr = \reset($attributes);
            return $next($info->with(arguments: $attr->arguments)->withAttribute(Bench::class, $attr));
        }

        # Dispatch batch starting event
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        # Load Filters
        $dataPointer = $info->getAttribute(DataPointer::class);

        # Run the test for each data set
        $results = [];
        $status = Status::Passed;
        foreach ($attributes as $index => $attr) {
            # Check Filters
            if ($dataPointer !== null && (
                $dataPointer->provider !== $index
                || ($dataPointer->dataset !== null && $dataPointer->dataset !== 0)
            )) {
                continue;
            }

            # Each attribute occupies the provider slot of the address, as it does for inline tests,
            # with a single data set inside it — matching the filter check above, so
            # `--filter=method:2` and `--filter=method:2:0` both select the third one.
            $newInfo = $info
                ->with(arguments: $attr->arguments, identity: $info->identity->toDataSet(dataProvider: $index, dataSet: 0))
                ->withAttribute(Bench::class, $attr);
            $label = "$index";

            # Dispatch dataset starting event
            $this->eventDispatcher->dispatch(
                new TestDataSetStarting($newInfo, $label, null, $index),
            );

            try {
                $result = $next($newInfo);
            } catch (\Throwable $throwable) {
                # Counts stay empty here; the aggregate's fold stamps each case's final status.
                $result = new TestResult(
                    info: $newInfo,
                    status: Status::Error,
                    failure: $throwable,
                );
            }

            # Dispatch dataset finished event
            $this->eventDispatcher->dispatch(
                new TestDataSetFinished($newInfo, $result, $label, null, $index),
            );

            unset($attr, $newInfo);
            $result->status->isFailure() and ($status = Status::Failed);
            $results[] = $result;
        }

        if ($results === []) {
            # No benchmark cases matched the filter — count as a single Risky test; leave counts empty
            # so the final status is stamped late by the TestRunner.
            $status->isFailure() or $status = Status::Risky;
            $finalResult = new TestResult(
                info: $info,
                status: $status,
                result: new \RuntimeException('No benchmark cases were provided.'),
            );
        } else {
            # Each benchmark case counts as a test, so the aggregate is the sum of their summaries,
            # each stamped with the case's final status.
            $summary = Summary::combine(\array_map(
                static fn(TestResult $r): Summary => $r->summary->withStatus($r->status),
                $results,
            ));
            $multiple = new MultipleResult($results);
            $finalResult = new TestResult(info: $info, status: $status, result: $multiple, attributes: [
                MultipleResult::class => $multiple,
            ], summary: $summary);
        }

        # Dispatch batch finished event
        $this->eventDispatcher->dispatch(new TestBatchFinished($info, $finalResult));

        return $finalResult;
    }
}
