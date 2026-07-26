<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Terminal;

use Internal\Container\ObjectContainer;
use Testo\Application\Internal\EventDispatcher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\EventListenerCollector;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Core\Value\Verbosity;
use Testo\Event\Message\MessageReceived;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Output\Terminal\Renderer\ColorMode;
use Testo\Output\Terminal\Renderer\OutputFormat;
use Testo\Output\Terminal\Renderer\Style;
use Testo\Output\Terminal\Renderer\TerminalLogger;
use Testo\Output\Terminal\TerminalPlugin;
use Testo\Test;
use Tests\Output\Stub\JUnit\SampleTestClass;

/**
 * How the terminal reporter attributes streamed output when tests interleave: it keys the in-flight
 * tests by {@see \Testo\Core\Context\TestIdentity::$id} taken off {@see MessageReceived}, so a switch
 * of test opens a fresh channel block named after its test instead of appending to a stranger's.
 */
#[Test]
#[Covers(TerminalPlugin::class)]
final class TerminalPluginTest
{
    public function interleavedTestsEachGetTheirOwnLabelledChannelBlock(): void
    {
        $first = self::makeTestInfo('passingTest');
        $second = self::makeTestInfo('failingTest');

        $output = self::capture(static function (EventDispatcher $dispatcher) use ($first, $second): void {
            $dispatcher->dispatch(new TestPipelineStarting($first));
            $dispatcher->dispatch(self::message("a1\n", $first));
            $dispatcher->dispatch(new TestPipelineStarting($second));
            $dispatcher->dispatch(self::message("b1\n", $second));
            $dispatcher->dispatch(self::message("a2\n", $first));
            $dispatcher->dispatch(self::message("b2\n", $second));
        });

        // Two tests in flight, so every block names its test — and no line is appended to the other's.
        Assert::string($output)->contains("· passingTest\na2\n");
        Assert::string($output)->contains("· failingTest\nb1\n");
        Assert::string($output)->contains("· failingTest\nb2\n");
        // Four lines from two alternating tests must never collapse into fewer blocks.
        Assert::same(\substr_count($output, '[stdout] '), 4);
    }

    public function sequentialTestsKeepThePlainUnlabelledHeader(): void
    {
        $first = self::makeTestInfo('passingTest');
        $second = self::makeTestInfo('failingTest');

        $output = self::capture(static function (EventDispatcher $dispatcher) use ($first, $second): void {
            $dispatcher->dispatch(new TestPipelineStarting($first));
            $dispatcher->dispatch(self::message("a1\n", $first));
            $dispatcher->dispatch(new TestPipelineFinished($first, new TestResult($first, Status::Passed)));
            $dispatcher->dispatch(new TestPipelineStarting($second));
            $dispatcher->dispatch(self::message("b1\n", $second));
        });

        // One test at a time is never ambiguous, so the header stays as terse as it always was.
        Assert::string($output)->notContains('·');
        Assert::same(\substr_count($output, '[stdout] '), 2);
    }

    public function messageOwnedByNoTestIsStillStreamed(): void
    {
        $output = self::capture(static function (EventDispatcher $dispatcher): void {
            $dispatcher->dispatch(new MessageReceived(
                new Message(0.0, 'stdout', Level::Info, "between tests\n"),
            ));
        });

        // Output that belongs to no test (suite/case setup) has no identity to group by, but dropping
        // it would lose framework-level output the user asked to see.
        Assert::string($output)->contains("between tests\n");
    }

    protected function setUp(): void
    {
        // Strip ANSI styling so assertions match raw text regardless of TTY config.
        Style::setColorsEnabled(false);
    }

    protected function tearDown(): void
    {
        Style::setColorsEnabled(true);
    }

    /**
     * Runs the callback against a plugin wired to a real dispatcher and returns what it wrote to the
     * terminal stream. Verbose, because that is the verbosity at which output streams live.
     */
    private static function capture(callable $scenario): string
    {
        $stdout = \fopen('php://memory', 'w+');
        $stderr = \fopen('php://memory', 'w+');
        \assert($stdout !== false && $stderr !== false);

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
            \fclose($stdout);
            \fclose($stderr);
        }
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
                definition: new CaseDefinition(
                    name: SampleTestClass::class,
                    type: 'test',
                    reflection: new \ReflectionClass(SampleTestClass::class),
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(SampleTestClass::class, $method)),
        );
    }
}
