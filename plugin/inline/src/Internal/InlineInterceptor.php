<?php

declare(strict_types=1);

namespace Testo\Inline\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
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
use Testo\Inline\TestInline;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Interceptor that runs the target method as a pure function with provided arguments and expected result.
 *
 * @internal
 * @psalm-internal Testo\Inline
 */
#[InterceptorOptions(
    order: InterceptorOptions::ORDER_DATA_PROVIDER,
    onConflict: ConflictPolicy::First,
    testType: TestType::TestInline,
)]
final readonly class InlineInterceptor implements TestRunInterceptor
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        /** @var TestInline[] $attributes */
        $attributes = $info->getAttribute(TestInline::class);
        if ($attributes === []) {
            return $next($info);
        }

        if (\count($attributes) === 1) {
            $inline = \reset($attributes);
            return $next($info->with(arguments: $inline->arguments)->withAttribute(TestInline::class, $inline));
        }

        # Dispatch batch starting event
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        # Load Filters
        $dataPointer = $info->getAttribute(DataPointer::class);

        # Run the test for each data set
        $results = [];
        $status = Status::Passed;
        foreach ($attributes as $index => $inline) {
            # Check Filters
            if ($dataPointer !== null && (
                $dataPointer->provider !== $index
                || ($dataPointer->dataset !== null && $dataPointer->dataset !== 0)
            )) {
                continue;
            }

            # Each attribute occupies the provider slot of the address, with a single data set inside
            # it — matching the filter check above, so `--filter=method:2` and `--filter=method:2:0`
            # both select the third one.
            $newInfo = $info
                ->with(arguments: $inline->arguments, identity: $info->identity->toDataSet(dataProvider: $index, dataSet: 0))
                ->withAttribute(TestInline::class, $inline);
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

            unset($inline, $newInfo);
            $result->status->isFailure() and ($status = Status::Failed);
            $results[] = $result;
        }

        if ($results === []) {
            # No inline cases matched the filter — count as a single Risky test; leave counts empty
            # so the final status is stamped late by the TestRunner.
            $status->isFailure() or $status = Status::Risky;
            $finalResult = new TestResult(
                info: $info,
                status: $status,
                result: new \RuntimeException('No inline cases were provided.'),
            );
        } else {
            # Each inline case counts as a test, so the aggregate is the sum of their summaries,
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
