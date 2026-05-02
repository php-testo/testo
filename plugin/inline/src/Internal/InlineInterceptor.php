<?php

declare(strict_types=1);

namespace Testo\Inline\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
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
            if ($dataPointer !== null && $dataPointer->provider !== $index) {
                continue;
            }

            $newInfo = $info->with(arguments: $inline->arguments)->withAttribute(TestInline::class, $inline);
            $label = "$index";

            # Dispatch dataset starting event
            $this->eventDispatcher->dispatch(
                new TestDataSetStarting($newInfo, $label, null, $index),
            );

            try {
                $result = $next($newInfo);
            } catch (\Throwable $throwable) {
                $result = new TestResult(info: $newInfo, status: Status::Error, failure: $throwable);
            }

            # Dispatch dataset finished event
            $this->eventDispatcher->dispatch(
                new TestDataSetFinished($newInfo, $result, $label, null, $index),
            );

            unset($inline, $newInfo);
            $result->status->isFailure() and ($status = Status::Failed);
            $results[] = $result;
        }

        $results = new MultipleResult($results);

        $finalResult = new TestResult(info: $info, status: $status, result: $results, attributes: [
            MultipleResult::class => $results,
        ]);

        # Dispatch batch finished event
        $this->eventDispatcher->dispatch(new TestBatchFinished($info, $finalResult));

        return $finalResult;
    }
}
