<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Internal\Path;
use Internal\Container\ObjectContainer;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Core\Value\Verbosity;
use Testo\Event\Message\MessageReceived;
use Testo\Event\Test\TestBatchFinished;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetFinished;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Output\Terminal\Renderer\ColorMode;
use Testo\Output\Terminal\Renderer\OutputFormat;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Output\Terminal\Renderer\TerminalLogger;
use Testo\Output\Terminal\TerminalPlugin;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

/**
 * How the terminal reporter keeps a line-oriented report readable when tests interleave: each test's
 * lines form one block, and a block of a test that is not the one streaming live waits for the stream
 * instead of cutting through someone else's group.
 */
#[Test]
#[Covers(TerminalPlugin::class)]
final class TerminalPluginTest
{
    public function interleavedDataProvidersEachRenderAsOneTree(): void
    {
        $first = self::makeTestInfo('passingTest');
        $second = self::makeTestInfo('failingTest');

        $output = self::capture(static function (EventDispatcher $dispatcher) use ($first, $second): void {
            $dispatcher->dispatch(new TestBatchStarting($first));
            $dispatcher->dispatch(new TestBatchStarting($second));
            self::dataSet($dispatcher, $first, 'a0');
            self::dataSet($dispatcher, $second, 'b0');
            $dispatcher->dispatch(new TestBatchFinished($first, self::passed($first)));
            $dispatcher->dispatch(new TestPipelineFinished($first, self::passed($first)));
            $dispatcher->dispatch(new TestBatchFinished($second, self::passed($second)));
            $dispatcher->dispatch(new TestPipelineFinished($second, self::passed($second)));
        });

        // Both batch headers with both data sets under whichever came last would be unreadable — each
        // tree has to come out whole: header, then its own data set.
        Assert::same(
            \array_values(\array_filter(\array_map(\trim(...), \explode("\n", $output)))),
            [
                '◆ passingTest',
                '✓ Dataset #0 [a0] (0ms)',
                '◆ failingTest',
                '✓ Dataset #0 [b0] (0ms)',
            ],
        );
    }

    public function aTestsStreamedOutputStaysInOnePieceWhileAnotherRuns(): void
    {
        $first = self::makeTestInfo('passingTest');
        $second = self::makeTestInfo('failingTest');

        $output = self::capture(static function (EventDispatcher $dispatcher) use ($first, $second): void {
            $dispatcher->dispatch(self::message("a1\n", $first));
            $dispatcher->dispatch(self::message("b1\n", $second));
            $dispatcher->dispatch(self::message("a2\n", $first));
            $dispatcher->dispatch(new TestPipelineFinished($first, self::passed($first)));
            $dispatcher->dispatch(self::message("b2\n", $second));
            $dispatcher->dispatch(new TestPipelineFinished($second, self::passed($second)));
        });

        // The first test took the stream, so everything of its own — output and result line — precedes
        // anything of the second, whose lines then follow adjacent to each other.
        $at = static fn(string $needle): int => (int) \strpos($output, $needle);
        Assert::true($at('a1') < $at('a2'), $output);
        Assert::true($at('a2') < $at('passingTest'), $output);
        Assert::true($at('passingTest') < $at('b1'), $output);
        Assert::true($at('b1') < $at('b2'), $output);
    }

    public function sequentialTestsAreUnaffected(): void
    {
        $first = self::makeTestInfo('passingTest');
        $second = self::makeTestInfo('failingTest');

        $output = self::capture(static function (EventDispatcher $dispatcher) use ($first, $second): void {
            $dispatcher->dispatch(self::message("a1\n", $first));
            $dispatcher->dispatch(new TestPipelineFinished($first, self::passed($first)));
            $dispatcher->dispatch(self::message("b1\n", $second));
            $dispatcher->dispatch(new TestPipelineFinished($second, self::passed($second)));
        });

        // One test at a time never waits, and each still opens its own channel header.
        Assert::same(\substr_count($output, '[stdout] '), 2);
        Assert::true((int) \strpos($output, 'a1') < (int) \strpos($output, 'b1'));
    }

    public function messageOwnedByNoTestIsStillStreamed(): void
    {
        $output = self::capture(static function (EventDispatcher $dispatcher): void {
            $dispatcher->dispatch(new MessageReceived(
                new Message(0.0, 'stdout', Level::Info, "between tests\n"),
            ));
        });

        // Output that belongs to no test (suite/case setup) has no block to join, but dropping it
        // would lose framework-level output the user asked to see.
        Assert::string($output)->contains("between tests\n");
    }

    /**
     * Runs the callback against a plugin wired to a real dispatcher and returns what it wrote to the
     * terminal stream. Verbose, because that is the verbosity at which output streams live.
     *
     * @param \Closure(EventDispatcher): void $scenario
     */
    private static function capture(\Closure $scenario): string
    {
        $stdout = \fopen('php://memory', 'w+');
        $stderr = \fopen('php://memory', 'w+');
        \assert($stdout !== false && $stderr !== false);

        # Constructing the plugin writes colorization into a process-global flag, so this has to be put
        # back: leaking `Never` would strip ANSI from every case that runs after this one.
        $colors = Style::areColorsEnabled();

        try {
            $logger = new TerminalLogger(OutputFormat::Compact, Verbosity::Verbose, $stdout, $stderr);
            $dispatcher = new EventDispatcher();
            $container = new ObjectContainer();
            $container->set($dispatcher, EventListenerCollector::class);
            (new TerminalPlugin($logger, ColorMode::Never))->configure($container);

            $scenario($dispatcher);

            \rewind($stdout);
            return (string) \stream_get_contents($stdout);
        } finally {
            Style::setColorsEnabled($colors);
            \fclose($stdout);
            \fclose($stderr);
        }
    }

    /**
     * Runs one data set of `$info`'s batch, start to finish.
     *
     * Addressed under the batch, as the data-provider interceptor builds it — a data set is a run of
     * its own, so it is only the batch's `pipelineId` that puts its lines in the batch's block.
     */
    private static function dataSet(EventDispatcher $dispatcher, TestInfo $info, string $key): void
    {
        $set = $info->with(identity: $info->identity->toDataSet(dataProvider: 0, dataSet: 0));

        $dispatcher->dispatch(new TestDataSetStarting($set, $key, null, 0));
        $dispatcher->dispatch(new TestDataSetFinished($set, self::passed($set), $key, null, 0));
    }

    private static function passed(TestInfo $info): TestResult
    {
        return new TestResult(info: $info, status: Status::Passed);
    }

    private static function message(string $content, TestInfo $owner): MessageReceived
    {
        return new MessageReceived(
            new Message(0.0, 'stdout', Level::Info, $content),
            $owner->identity,
        );
    }

    /**
     * @param non-empty-string $method
     */
    private static function makeTestInfo(string $method): TestInfo
    {
        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Output/Unit'),
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, $method)),
        );
    }
}
