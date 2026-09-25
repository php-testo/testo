<?php

declare(strict_types=1);

namespace Tests\Bench\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\TestState;
use Testo\Bench;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Internal\Pipeline\BenchVerdictInterceptor;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Test;

#[Test]
#[Covers(BenchVerdictInterceptor::class)]
final class BenchVerdictInterceptorTest
{
    public function aSlowerCurrentFailsTheTestAndKeepsTheBenchResult(): void
    {
        $bench = self::benchResult(2.0, 1.0);

        [$result, $state] = self::run($bench);

        Assert::same($result->status, Status::Failed);
        Assert::instanceOf($result->failure, AssertionException::class);
        Assert::string($result->failure->getMessage())->contains("'alt' is 100.0% faster");
        Assert::same($result->result, $bench);
        Assert::same(\count($state->history), 1);
        Assert::false($state->history[0]->isSuccess());
    }

    public function theFastestCurrentPassesAndRecordsASuccessfulAssertion(): void
    {
        $bench = self::benchResult(1.0, 2.0);

        [$result, $state] = self::run($bench);

        Assert::same($result->status, Status::Passed);
        Assert::null($result->failure);
        Assert::same($result->result, $bench);
        Assert::same(\count($state->history), 1);
        Assert::true($state->history[0]->isSuccess());
    }

    public function aResultWithoutBenchDataPassesThroughUntouched(): void
    {
        [$result, $state] = self::run('not a benchmark');

        Assert::same($result->status, Status::Passed);
        Assert::same($state->history, []);
    }

    /**
     * Runs the interceptor with a fresh assertion state swapped in, so the verdict it records stays
     * out of the enclosing test's own history.
     *
     * @return array{TestResult, TestState}
     */
    private static function run(mixed $value): array
    {
        $state = new TestState();
        $previous = StaticState::swap($state);
        try {
            $result = (new BenchVerdictInterceptor())->runTest(
                self::createTestInfo(),
                static fn(TestInfo $info): TestResult => new TestResult($info, Status::Passed, $value),
            );
        } finally {
            StaticState::swap($previous);
        }

        return [$result, $state];
    }

    private static function benchResult(float ...$favg): BenchResult
    {
        $names = ['current', 'alt'];

        return new BenchResult(
            cases: \array_map(
                static fn(int $i): CaseSet => new CaseSet(name: $names[$i], iterations: []),
                \array_keys($favg),
            ),
            results: \array_map(
                static fn(float $favg): CaseResult => new CaseResult(
                    mean: $favg,
                    med: $favg,
                    rstdev: 0.0,
                    rejected: 0,
                    favg: $favg,
                    frstdev: 0.0,
                ),
                $favg,
            ),
        );
    }

    private static function createTestInfo(): TestInfo
    {
        return new TestInfo(
            name: 'target',
            caseInfo: new CaseInfo(
                definition: new CaseDefinition(name: 'TestCase', type: 'bench', file: Path::create(__FILE__)),
                suiteIdentity: new SuiteIdentity('Bench/Unit'),
            ),
            testDefinition: new TestDefinition(reflection: new \ReflectionFunction(static fn() => null)),
            attributes: [Bench::class => new Bench([static fn(): int => 1], tolerance: 0.02)],
        );
    }
}
