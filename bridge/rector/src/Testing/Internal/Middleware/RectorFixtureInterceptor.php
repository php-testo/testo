<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Rector\Testing\Fixture\FixtureFileFinder;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\MultipleResult;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
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
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $this->eventDispatcher->dispatch(new TestBatchStarting($info));

        $reflection = $info->caseInfo->definition->reflection;
        $fixtures = $reflection === null ? [] : $this->resolveFixtures($reflection);

        $results = [];
        $status = Status::Passed;
        $error = null;

        try {
            if ($reflection !== null && $fixtures !== []) {
                $runner = new RectorRunner([$reflection->getName()]);

                $num = -1;
                foreach ($fixtures as $label => $path) {
                    ++$num;
                    $dsInfo = $info->with(arguments: [$runner, $path]);

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
     * Resolves the rule's {@see TestRectorFixtures} paths (relative to the rule file) to a map of
     * `fixture name => absolute path`.
     *
     * @return array<non-empty-string, non-empty-string>
     */
    private function resolveFixtures(\ReflectionClass $rule): array
    {
        $attributes = $rule->getAttributes(TestRectorFixtures::class);
        if ($attributes === []) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();
        $dir = \dirname((string) $rule->getFileName());

        $map = [];
        foreach ($attribute->paths as $path) {
            $full = $dir . '/' . $path;
            if (\is_dir($full)) {
                foreach (FixtureFileFinder::yieldDirectory($full) as [$file]) {
                    $map[\basename($file)] = $file;
                }
            } elseif (\is_file($full)) {
                $map[\basename($full)] = $full;
            }
        }

        return $map;
    }
}
