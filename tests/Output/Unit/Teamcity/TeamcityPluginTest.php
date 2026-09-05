<?php

declare(strict_types=1);

namespace Tests\Output\Unit\Teamcity;

use Internal\Container\ObjectContainer;
use Internal\Path;
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
use Testo\Core\Value\Status;
use Testo\Event\Test\TestBatchStarting;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Event\Test\TestPipelineFinished;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Event\Test\TestStarting;
use Testo\Output\Teamcity\TeamcityPlugin;
use Testo\Output\Terminal\Renderer\ColorMode;
use Testo\Test;
use Tests\Output\Stub\Teamcity\SampleTestClass;

/**
 * When the plugin announces a test as started to the CI server / IDE — the timing an IDE relies on to
 * show a running spinner between `testStarted` and `testFinished`.
 */
#[Test]
#[Covers(TeamcityPlugin::class)]
final class TeamcityPluginTest
{
    public function aRegularTestIsAnnouncedStartedWhenItsBodyBeginsNotOnlyWhenItFinishes(): void
    {
        $info = self::makeInfo('passingTest');

        // Stop at TestStarting: the body is now running but has not finished, and produced no output.
        // A long, silent test sits here for its whole duration — so testStarted must already be out.
        $output = self::drive(static function (EventDispatcher $dispatcher) use ($info): void {
            $dispatcher->dispatch(new TestPipelineStarting($info));
            $dispatcher->dispatch(new TestStarting($info));
        });

        Assert::string($output)->contains('##teamcity[testStarted');
        Assert::string($output)->notContains('##teamcity[testFinished');
    }

    public function aSilentRegularTestStartsBeforeItFinishes(): void
    {
        $info = self::makeInfo('passingTest');

        $output = self::drive(static function (EventDispatcher $dispatcher) use ($info): void {
            $dispatcher->dispatch(new TestPipelineStarting($info));
            $dispatcher->dispatch(new TestStarting($info));
            $dispatcher->dispatch(new TestPipelineFinished($info, self::passed($info)));
        });

        $started = \strpos($output, '##teamcity[testStarted');
        $finished = \strpos($output, '##teamcity[testFinished');

        Assert::true($started !== false, 'testStarted must be emitted');
        Assert::true($finished !== false, 'testFinished must be emitted');
        Assert::true($started < $finished, 'testStarted must precede testFinished');
    }

    public function aDataSetBodyDoesNotEmitASecondTestStarted(): void
    {
        $info = self::makeInfo('passingTest');
        $dataSet = $info->with(identity: $info->identity->toDataSet(dataProvider: 0, dataSet: 0));

        // A data set gets its testStarted eagerly from TestDataSetStarting; the TestStarting fired for
        // its body must not add a second one, or the IDE tree would carry a duplicate node.
        $output = self::drive(static function (EventDispatcher $dispatcher) use ($info, $dataSet): void {
            $dispatcher->dispatch(new TestPipelineStarting($info));
            $dispatcher->dispatch(new TestBatchStarting($info));
            $dispatcher->dispatch(new TestDataSetStarting($dataSet, '0', 0, 0));
            $dispatcher->dispatch(new TestStarting($dataSet));
        });

        Assert::same(\substr_count($output, '##teamcity[testStarted'), 1);
    }

    /**
     * Wires a fresh plugin to a fresh dispatcher writing to an in-memory stream, runs the scenario, and
     * returns everything the plugin published.
     *
     * @param \Closure(EventDispatcher): void $scenario
     */
    private static function drive(\Closure $scenario): string
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        try {
            $dispatcher = new EventDispatcher();
            $container = new ObjectContainer();
            $container->set($dispatcher, EventListenerCollector::class);
            (new TeamcityPlugin(ColorMode::Never, $stream))->configure($container);

            $scenario($dispatcher);

            \rewind($stream);
            $output = \stream_get_contents($stream);
        } finally {
            \fclose($stream);
        }

        return $output === false ? '' : $output;
    }

    private static function passed(TestInfo $info): TestResult
    {
        return new TestResult(info: $info, status: Status::Passed, attributes: ['duration' => 0]);
    }

    /**
     * @param non-empty-string $method Method of {@see SampleTestClass} backing the test definition.
     */
    private static function makeInfo(string $method): TestInfo
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
