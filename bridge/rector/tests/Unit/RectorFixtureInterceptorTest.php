<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Assert;
use Testo\Bridge\Rector\Testing\Internal\Middleware\RectorFixtureInterceptor;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Common\Messenger\Channel;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Data\MultipleResult;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\EscapingFixturesRule;

#[Test]
#[Covers(RectorFixtureInterceptor::class)]
final class RectorFixtureInterceptorTest
{
    /**
     * A rule that fails before any fixture runs (here, its fixtures path escapes the project and
     * trips the containment guard) must surface that failure as an errored data set, not swallow it:
     * reporters only open and close the batch node, so an error left on the batch alone is invisible.
     */
    public function aSetupFailureIsReportedAsAnErroredDataSet(): void
    {
        $dispatcher = self::createDispatcher();
        $interceptor = new RectorFixtureInterceptor($dispatcher, self::createMessenger());
        $info = self::infoFor(EscapingFixturesRule::class);
        $next = static fn(TestInfo $i): TestResult => new TestResult(info: $i, status: Status::Passed);

        $result = $interceptor->runTest($info, $next);

        Assert::same($result->status, Status::Failed);

        $multiple = $result->getAttribute(MultipleResult::class);
        Assert::true($multiple instanceof MultipleResult);
        Assert::count($multiple->results, 1);

        $errored = $multiple->results[0];
        Assert::same($errored->status, Status::Error);
        Assert::instanceOf($errored->failure, \LogicException::class);

        # The failure rode a data set node, which is what makes every reporter render it.
        $started = \array_filter(
            $dispatcher->dispatched,
            static fn(object $e): bool => $e instanceof TestDataSetStarting,
        );
        Assert::count($started, 1);
    }

    private static function infoFor(string $ruleClass): TestInfo
    {
        $class = new \ReflectionClass($ruleClass);
        $caseDefinition = new CaseDefinition(
            name: $class->getShortName(),
            type: 'rector-fixture',
            file: Path::create((string) $class->getFileName()),
            reflection: $class,
        );
        $caseInfo = new CaseInfo(suiteIdentity: new SuiteIdentity('Bridge/Rector'), definition: $caseDefinition);
        $testDefinition = new TestDefinition(reflection: new \ReflectionMethod($ruleClass, 'fixture'));

        return new TestInfo(name: 'fixture', caseInfo: $caseInfo, testDefinition: $testDefinition);
    }

    /**
     * @return EventDispatcherInterface&object{dispatched: list<object>}
     */
    private static function createDispatcher(): EventDispatcherInterface
    {
        return new class() implements EventDispatcherInterface {
            /** @var list<object> */
            public array $dispatched = [];

            #[\Override]
            public function dispatch(object $event): object
            {
                $this->dispatched[] = $event;
                return $event;
            }
        };
    }

    /** A messenger that is never touched on the setup-failure path; any call is a test bug. */
    private static function createMessenger(): Messenger
    {
        return new class() implements Messenger {
            #[\Override]
            public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void
            {
                throw new \LogicException('messenger must not be used on the setup-failure path');
            }

            #[\Override]
            public function channel(string $name): Channel
            {
                throw new \LogicException('messenger must not be used on the setup-failure path');
            }

            #[\Override]
            public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed
            {
                throw new \LogicException('messenger must not be used on the setup-failure path');
            }

            #[\Override]
            public function fork(\Closure $fork, bool $holdEvents = false): mixed
            {
                throw new \LogicException('messenger must not be used on the setup-failure path');
            }

            #[\Override]
            public function getMessages(): MessageLog
            {
                return new MessageLog();
            }
        };
    }
}
