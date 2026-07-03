<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Runner;

use Testo\Application\Application;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;
use Testo\Application\Internal\Runner\CaseRunner;
use Testo\Application\Internal\Runner\SuiteRunner;
use Testo\Application\Internal\Runner\TestRunner;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\TestResult;
use Testo\Core\Value\Status;
use Testo\Test;
use Tests\Application\Stub\Pipeline\FailingInterceptor;

/**
 * Pins how a throw *from inside an interceptor* (not from the test body) is surfaced
 * by the runners, depending on the pipeline stage it happens at.
 *
 * Findings this test locks in:
 *
 *  - A throw in {@see \Testo\Pipeline\Middleware\TestRunInterceptor::runTest()} (before or after
 *    `$next()`) is caught by {@see TestRunner} and turned into a {@see Status::Aborted} test result.
 *    {@see Status::isFailure()} stays `false` for `Aborted` (so retry/repeat never retry an abort),
 *    but {@see CaseRunner} escalates the case to {@see Status::Failed} on an aborted test, so the
 *    suite/run status and the process exit code agree with the "FAILED" the reporters render.
 *
 *  - A throw in {@see \Testo\Pipeline\Middleware\TestCaseRunInterceptor::runTestCase()} escapes the
 *    case pipeline, is caught by {@see SuiteRunner} and marks the suite {@see Status::Error}, which
 *    *does* fail the run — but the individual test results are dropped from the summary.
 *
 * @see \Testo\Core\Value\Status::Aborted
 */
#[Test]
#[Covers(TestRunner::class)]
#[Covers(SuiteRunner::class)]
final class PipelineFailureStatusTest
{
    private const STUB_DIR = __DIR__ . '/../../Stub/Pipeline';

    /**
     * A broken test-level interceptor aborts two tests; the run must fail (exit code 1) so it
     * agrees with the "FAILED" banner the reporters print, instead of silently exiting 0.
     */
    public function testLevelAbortFailsTheRun(): void
    {
        $result = self::runScenario('TestStageScenarios.php');

        Assert::same($result->status, Status::Failed);
        Assert::false($result->status->isSuccessful(), 'An aborted test must not let the run exit 0.');
    }

    /**
     * The aborted tests are still counted and rendered alongside the surviving passing test.
     */
    public function testLevelAbortIsCountedAndVisibleInTheSummary(): void
    {
        $result = self::runScenario('TestStageScenarios.php');

        Assert::same($result->summary->total(), 3);
        Assert::same($result->summary->count(Status::Passed), 1);
        Assert::same($result->summary->count(Status::Aborted), 2);

        $statuses = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    \assert($test instanceof TestResult);
                    $statuses[$test->info->name] = $test->status;
                }
            }
        }

        Assert::same($statuses['throwsBeforeNext'] ?? null, Status::Aborted);
        Assert::same($statuses['throwsAfterNext'] ?? null, Status::Aborted);
        Assert::same($statuses['passesCleanly'] ?? null, Status::Passed);
    }

    /**
     * A throw before `$next()` at case level fails the run: none of the tests run and the
     * suite is marked Error.
     */
    public function caseLevelThrowBeforeNextFailsTheRun(): void
    {
        $result = self::runScenario('CaseBeforeScenario.php');

        Assert::same($result->status, Status::Failed);
        Assert::false($result->status->isSuccessful());
        Assert::same($result->summary->total(), 0);
    }

    /**
     * A throw after `$next()` at case level also fails the run, but the tests that already ran
     * (and passed) vanish from the summary because the case result is discarded on unwind.
     */
    public function caseLevelThrowAfterNextFailsTheRunButLosesResults(): void
    {
        $result = self::runScenario('CaseAfterScenario.php');

        Assert::same($result->status, Status::Failed);
        Assert::false($result->status->isSuccessful());
        Assert::same($result->summary->total(), 0);
    }

    /**
     * Sanity guard: without the interceptor exploding, the stub with clean/marked methods is not
     * special — the message referenced here keeps the stubs and the interceptor in sync.
     */
    public function interceptorMessageIsStable(): void
    {
        Assert::same(FailingInterceptor::MESSAGE, 'FailingInterceptor exploded');
    }

    /**
     * Run a single stub file through a nested application and return the whole {@see RunResult}.
     *
     * No plugin is registered: {@see \Tests\Application\Stub\Pipeline\FailPipeline} self-binds
     * {@see FailingInterceptor} via {@see \Testo\Pipeline\Attribute\FallbackInterceptor}, so the
     * attribute alone drives the failure.
     *
     * @param non-empty-string $stubFile File name inside the Pipeline stub directory.
     */
    private static function runScenario(string $stubFile): RunResult
    {
        $app = Application::createFromInput();
        $app->getContainer()->set(
            new ApplicationConfig(
                src: [],
                suites: [
                    new SuiteConfig(
                        name: 'PipelineFailure',
                        location: new FinderConfig(include: [self::STUB_DIR . '/' . $stubFile]),
                    ),
                ],
            ),
            ApplicationConfig::class,
        );

        return $app->run();
    }
}
