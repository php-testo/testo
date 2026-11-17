<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Internal\Path;
use Testo\Application\Config\SuiteConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Output\Terminal\ProgramStatus;
use Testo\Output\Terminal\Renderer\RunStatus;
use Testo\Test;

/**
 * The run's percent is split into segments: an equal share per located suite, a suite's share per test
 * case, a case's share per test.
 */
#[Test]
#[Covers(RunStatus::class)]
final class RunStatusTest
{
    public function progressAdvancesThroughSuiteCaseAndTestSegments(): void
    {
        $first = self::suite('A', self::case(2), self::case(1));
        $second = self::suite('B', self::case(4));

        $output = self::capture(static function (RunStatus $run) use ($first, $second): void {
            $run->suitesLocated([self::config('A'), self::config('B')]);
            self::runSuite($run, $first);
            self::runSuite($run, $second);
        });

        // A is 0–50: its first case takes 0–25 in two tests, its second 25–50 in one. B is 50–100, and
        // its start repeats 50 to name the new suite.
        Assert::same(self::progress($output), [0, 12, 25, 50, 50, 62, 75, 87, 100]);
    }

    public function aFinishedRunReachesExactlyOneHundred(): void
    {
        $suite = self::suite('A', ...\array_map(static fn(): CaseDefinition => self::case(1), \range(1, 23)));

        $output = self::capture(static function (RunStatus $run) use ($suite): void {
            $run->suitesLocated([self::config('A')]);
            self::runSuite($run, $suite);
        });

        Assert::same(\array_slice(self::progress($output), -1), [100]);
    }

    public function progressNeverStepsBackAtASegmentBoundary(): void
    {
        $names = \array_map(static fn(int $i): string => "S$i", \range(1, 9));
        $suites = \array_map(
            static fn(string $name): SuiteInfo => self::suite(
                $name,
                ...\array_map(static fn(): CaseDefinition => self::case(1), \range(1, 10)),
            ),
            $names,
        );

        $output = self::capture(static function (RunStatus $run) use ($names, $suites): void {
            $run->suitesLocated(\array_map(self::config(...), $names));
            foreach ($suites as $suite) {
                self::runSuite($run, $suite);
            }
        });

        $progress = self::progress($output);
        $sorted = $progress;
        \sort($sorted);
        Assert::same($progress, $sorted);
        Assert::same(\end($progress), 100);
    }

    public function aRepeatedSuiteNameTakesItsNextSlot(): void
    {
        $first = self::suite('A', self::case(1));
        $second = self::suite('A', self::case(1));

        $output = self::capture(static function (RunStatus $run) use ($first, $second): void {
            $run->suitesLocated([self::config('A'), self::config('A')]);
            self::runSuite($run, $first);
            self::runSuite($run, $second);
        });

        Assert::same(self::progress($output), [0, 50, 100]);
    }

    public function suitesSkippedForHavingNoCasesLeaveTheirShareToTheRest(): void
    {
        $last = self::suite('C', self::case(1));

        $output = self::capture(static function (RunStatus $run) use ($last): void {
            $run->suitesLocated([self::config('A'), self::config('B'), self::config('C')]);
            self::runSuite($run, $last);
        });

        // A filtered run that keeps only the last suite still goes from 0 to 100.
        Assert::same(self::progress($output), [0, 100]);
    }

    public function aCaseRunningMoreTestsThanCountedStaysInsideItsSegment(): void
    {
        $suite = self::suite('A', self::case(1), self::case(1));

        $output = self::capture(static function (RunStatus $run) use ($suite): void {
            $run->suitesLocated([self::config('A')]);
            $run->suiteStarted($suite);
            [$first] = $suite->testCases->getCases();
            $run->caseStarted(self::caseInfo($first));
            $run->testFinished();
            $run->testFinished();
        });

        Assert::same(self::progress($output), [0, 50]);
    }

    public function withoutLocatedSuitesTheProgressIsIndeterminate(): void
    {
        $suite = self::suite('A', self::case(2));

        $output = self::capture(static fn(RunStatus $run) => self::runSuite($run, $suite));

        Assert::same(self::progress($output), []);
        Assert::string($output)->contains('state=working');
    }

    public function aSuccessfulRunIsDone(): void
    {
        $output = self::finish(Status::Passed, ['Passed' => 3]);

        Assert::string($output)->contains('state=done:app=testo:msg=' . \base64_encode('3 passed'));
    }

    public function aFailedRunIsAnError(): void
    {
        $output = self::finish(Status::Failed, ['Passed' => 3, 'Failed' => 1]);

        Assert::string($output)->contains('state=error:app=testo:msg=' . \base64_encode('1 failed of 4'));
    }

    public function anAbortedTestCountsAsFailed(): void
    {
        $output = self::finish(Status::Failed, ['Passed' => 3, 'Aborted' => 2]);

        Assert::string($output)->contains('state=error:app=testo:msg=' . \base64_encode('2 failed of 5'));
    }

    public function skippedTestsAreNamedInTheOutcome(): void
    {
        $output = self::finish(Status::Passed, ['Skipped' => 4]);

        Assert::string($output)->contains('state=done:app=testo:msg=' . \base64_encode('0 passed, 4 skipped'));
    }

    public function anEmptyRunIsAnError(): void
    {
        $output = self::finish(Status::Risky, []);

        Assert::string($output)->contains('state=error:app=testo:msg=' . \base64_encode('No tests executed'));
    }

    /**
     * @param array<non-empty-string, int<0, max>> $counts
     */
    private static function finish(Status $status, array $counts): string
    {
        return self::capture(static fn(RunStatus $run) => $run->sessionFinished(
            new RunResult([], status: $status, summary: new Summary($counts)),
        ));
    }

    private static function runSuite(RunStatus $run, SuiteInfo $suite): void
    {
        $run->suiteStarted($suite);
        foreach ($suite->testCases->getCases() as $case) {
            $run->caseStarted(self::caseInfo($case));
            foreach ($case->tests->getTests() as $_) {
                $run->testFinished();
            }
        }
    }

    /**
     * @return list<int>
     */
    private static function progress(string $output): array
    {
        \preg_match_all('/progress=(\d+)/', $output, $matches);

        return \array_map(\intval(...), $matches[1]);
    }

    /**
     * @param non-empty-string $name
     */
    private static function config(string $name): SuiteConfig
    {
        return new SuiteConfig($name, location: []);
    }

    /**
     * @param non-empty-string $name
     */
    private static function suite(string $name, CaseDefinition ...$cases): SuiteInfo
    {
        return new SuiteInfo($name, CaseDefinitions::fromArray(...$cases));
    }

    private static function case(int $tests): CaseDefinition
    {
        $test = new TestDefinition(new \ReflectionFunction(static function (): void {}));

        return new CaseDefinition(
            name: null,
            type: 'test',
            file: Path::create(__FILE__),
            tests: TestDefinitions::fromArray(...\array_fill(0, $tests, $test)),
        );
    }

    private static function caseInfo(CaseDefinition $case): CaseInfo
    {
        return new CaseInfo($case, new SuiteIdentity('A'));
    }

    /**
     * @param \Closure(RunStatus): void $scenario
     */
    private static function capture(\Closure $scenario): string
    {
        $stream = \fopen('php://memory', 'w+');
        \assert($stream !== false);

        try {
            $scenario(new RunStatus(new ProgramStatus($stream)));

            \rewind($stream);
            return (string) \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }
    }
}
