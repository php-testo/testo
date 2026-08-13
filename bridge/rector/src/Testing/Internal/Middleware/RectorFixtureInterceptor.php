<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal\Middleware;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Bridge\Rector\Testing\Internal\FixtureResolver;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;
use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\MultipleResult;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Filter\DataPointer;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;

/**
 * Fans a rule's single probe test (defined by {@see RectorFixtureFinder}) into one data set per
 * fixture, mirroring how `DataProviderInterceptor` expands a `#[DataProvider]` test. It builds one
 * {@see RectorRunner} for the rule, then runs each fixture through the normal test pipeline (so the
 * Assert plugin records assertions and each fixture is reported as its own data set).
 *
 * @internal
 * @psalm-internal Testo\Bridge\Rector
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DATA_PROVIDER, testType: RectorFixtureInterceptor::TYPE)]
final readonly class RectorFixtureInterceptor implements TestRunInterceptor
{
    /** @var non-empty-string The synthesized test type for rule fixtures. */
    public const TYPE = 'rector-fixture';

    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        $reflection = $info->caseInfo->definition->reflection;

        $results = [];
        $status = Status::Passed;
        $error = null;

        try {
            // Resolved inside the try so a path-containment violation surfaces as a test error
            // rather than an uncaught throw out of the interceptor.
            $fixtures = $reflection === null ? [] : $this->resolveFixtures($reflection);

            if ($reflection !== null && $fixtures !== []) {
                $runner = new RectorRunner($this->messenger, [$reflection->getName()]);

                # A fixture occupies the data set slot of the address, so `--filter=Rule::fixture:0:2`
                # selects the third fixture — the coordinates the IDE sends back for one data set.
                $dataPointer = $info->getAttribute(DataPointer::class);

                $num = -1;
                foreach ($fixtures as $label => $path) {
                    ++$num;
                    if ($dataPointer !== null && (
                        ($dataPointer->provider !== 0)
                        || ($dataPointer->dataset !== null && $dataPointer->dataset !== $num)
                    )) {
                        continue;
                    }

                    # Each fixture needs its own address, or consumers that key on it collide: TeamCity
                    # would reuse the batch's node for every data set and nest none of them under it.
                    $dsInfo = $info->with(
                        arguments: [$runner, $path],
                        identity: $info->identity->toDataSet(dataProvider: 0, dataSet: $num),
                    );

                    $this->eventDispatcher->dispatch(new TestDataSetStarting($dsInfo, $label, null, $num));
                    try {
                        $result = $next($dsInfo);
                    } catch (\Throwable $e) {
                        $result = new TestResult(info: $dsInfo, status: Status::Error, failure: $e);
                    }
                    $result->status->isFailure() and $status = Status::Failed;
                    $this->eventDispatcher->dispatch(new TestDataSetFinished($dsInfo, $result, $label, null, $num));

                    $results[] = $result;
                }
            }
        } catch (\Throwable $e) {
            $status = Status::Error;
            $error = $e;
        }

        if ($results === []) {
            $final = new TestResult(
                info: $info,
                status: $status->isFailure() ? $status : Status::Risky,
                result: $error ?? new \RuntimeException('No fixtures were found for this rule.'),
            );
        } else {
            $multiple = new MultipleResult($results);
            $final = new TestResult(
                info: $info,
                status: $status,
                result: $multiple,
                attributes: [MultipleResult::class => $multiple],
                summary: Summary::combine(\array_map(
                    static fn(TestResult $r): Summary => $r->summary->withStatus($r->status),
                    $results,
                )),
            );
        }

        $this->eventDispatcher->dispatch(new TestBatchFinished($info, $final));

        return $final;
    }

    /**
     * Resolves the rule's {@see TestRectorFixtures} paths to a map of `fixture name => path`,
     * guarding against any path escaping the working directory ({@see FixtureResolver}).
     *
     * @return array<non-empty-string, Path>
     */
    private function resolveFixtures(\ReflectionClass $rule): array
    {
        $attributes = $rule->getAttributes(TestRectorFixtures::class);
        if ($attributes === []) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();

        $cwd = \getcwd();
        $cwd === false and throw new \RuntimeException('Cannot determine the working directory.');

        $ruleDir = Path::create((string) $rule->getFileName())->parent();

        return (new FixtureResolver($cwd))->resolve($ruleDir, $attribute->paths);
    }
}
