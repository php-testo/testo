<?php

declare(strict_types=1);

namespace Testo\Data\Internal;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Filter\DataPointer;
use Testo\Core\Value\Status;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Data\DataZip;
use Testo\Data\MultipleResult;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Interceptor that handles data providers for tests.
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DATA_PROVIDER, onConflict: ConflictPolicy::First)]
final class DataProviderInterceptor implements TestRunInterceptor
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        // Dispatch batch starting event
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        $results = [];
        $status = Status::Passed;
        try {
            # Collect attributes
            $attributes = $info->testDefinition->reflection
                ->getAttributes(DataProviderAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

            # Check Filters
            $dataPointer = $info->getAttribute(DataPointer::class);

            $providerNum = -1;
            foreach ($attributes as $pNum => $attribute) {
                ++$providerNum;
                if ($dataPointer !== null && $dataPointer->provider !== $pNum) {
                    continue;
                }

                $attr = $attribute->newInstance();

                $datasets = match (true) {
                    $attr instanceof DataProvider => self::fromDataProvider($info, $attr),
                    $attr instanceof DataSet => [($attr->name ?? 0) => $attr->arguments],
                    $attr instanceof DataZip => self::fromDataZip($info, $attr),
                    default => throw new \RuntimeException('Unknown Data Provider Attribute type.'),
                };

                # Handle each data set
                $results = [];
                $num = -1;
                foreach ($datasets as $k => $dataset) {
                    ++$num;
                    if ($dataPointer !== null && $dataPointer->dataset !== null && $dataPointer->dataset !== $num) {
                        continue;
                    }

                    \is_array($dataset) or throw new \InvalidArgumentException(
                        'Each data set must be an array of arguments.',
                    );

                    # Determine unique label for the data set
                    $label = (string) $k;
                    // $i = 0;
                    // while (\array_key_exists($label, $results)) {
                    //     ++$i;
                    //     $label = "$k~$i";
                    // }


                    $result = $this->run($info, $next, $label, \count($attributes) === 1 ? null : $providerNum, $num, $dataset);
                    $result->status->isFailure() and $status = Status::Failed;
                    $results[] = $result;
                }
            }
        } catch (\Throwable $e) {
            $status = Status::Error;
            throw $e;
        } finally {
            if ($results === []) {
                # No data sets were run, mark as Risky
                $status->isFailure() or $status = Status::Risky;

                $finalResult = new TestResult(
                    info: $info,
                    status: $status,
                    result: $e ?? new \RuntimeException('No data sets were provided by the data provider.'),
                );
            } else {
                $summary = new MultipleResult($results);
                $finalResult = new TestResult(
                    info: $info,
                    status: $status,
                    result: $e ?? $summary,
                    attributes: [
                        MultipleResult::class => $summary,
                    ],
                );
            }

            // Dispatch batch finished event
            $this->eventDispatcher->dispatch(new TestBatchFinished($info, $finalResult));
        }

        return $finalResult;
    }

    /**
     * Run a single data set.
     *
     * @param TestInfo $info Test information.
     * @param non-empty-string $label Unique label for the data set.
     * @param int<0, max>|null $providerNum Data provider number or null if only one.
     * @param int<0, max> $datasetNum Data set number.
     * @param callable(TestInfo): TestResult $next Next interceptor or core logic to run the test.
     */
    public function run(
        TestInfo $info,
        callable $next,
        string $label,
        ?int $providerNum,
        int $datasetNum,
        array $arguments,
    ): TestResult {
        $newInfo = $info->with(
            arguments: $arguments,
        );

        // Dispatch dataset starting event
        $this->eventDispatcher->dispatch(new TestDataSetStarting($newInfo, $label, $providerNum, $datasetNum));

        try {
            $result = $next($newInfo);
        } catch (\Throwable $throwable) {
            $result = new TestResult(
                info: $newInfo,
                status: Status::Error,
                failure: $throwable,
            );
        }

        // Dispatch dataset finished event
        $this->eventDispatcher->dispatch(new TestDataSetFinished($newInfo, $result, $label, $providerNum, $datasetNum));

        return $result;
    }

    /**
     * Extract data sets from a DataProvider attribute.
     */
    private static function fromDataProvider(TestInfo $info, DataProvider $attribute): iterable
    {
        $provider = $attribute->provider;

        # String provider definition means the method name in the test class
        $ref = $info->testDefinition->reflection;
        if (\is_string($provider) && $ref instanceof \ReflectionMethod) {
            /** @var \ReflectionClass $class */
            $class = $ref->getDeclaringClass();

            if ($class->hasMethod($provider)) {
                $m = $class->getMethod($provider);
                $provider = match (true) {
                    $m->isStatic() => $m->getClosure(null),
                    default => static fn () => $m->getClosure($info->caseInfo->instance->getInstance()),
                };
            }

            \is_callable($provider) or throw new \InvalidArgumentException(
                'DataProvider provider must be a callable or method name string.',
            );
        }

        # Fetch data sets from the provider
        $datasets = $provider();
        \is_iterable($datasets) or throw new \InvalidArgumentException(
            'Data provider must return an iterable of data sets.',
        );

        return $datasets;
    }

    /**
     * Extract data sets from a DataZip attribute.
     */
    private static function fromDataZip(TestInfo $info, DataZip $attr): iterable
    {
        $generators = \array_map(
            static fn ($providerAttr): DeferredGenerator => DeferredGenerator::fromHandler(
                static function () use ($info, $providerAttr) {
                    yield from self::fromDataProvider($info, $providerAttr);
                },
            ),
            $attr->providers,
        );

        dataset:
        $allFinished = true;
        $dataSet = [];
        $key = [];
        foreach ($generators as $gen) {
            if ($gen->valid()) {
                $allFinished = false;
                $dataSet[] = $gen->current();
                $key[] = $gen->key();
                $gen->next();
            } else {
                $key[] = '';
                $dataSet[] = null;
            }
        }

        if ($allFinished) {
            return;
        }

        yield \implode(':', $key) => \array_merge(...$dataSet);
        goto dataset;
    }
}
