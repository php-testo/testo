<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Pipeline\Internal\OutputInterceptor;
use Testo\Test;

#[Test]
#[Covers(OutputInterceptor::class)]
final class OutputInterceptorTest
{
    public function runTestCallsNextWithInfoAndReturnsResult(): void
    {
        $testInfo = $this->makeTestInfo();
        $messenger = new TestMessenger();
        $interceptor = new OutputInterceptor($messenger);
        $testResult = new TestResult($testInfo, Status::Passed);

        $received = null;
        $result = $interceptor->runTest($testInfo, static function (TestInfo $info) use (&$received, $testResult): TestResult {
            $received = $info;
            return $testResult;
        });

        Assert::same($received, $testInfo);
        Assert::same($result->status, Status::Passed);
    }

    public function runTestReturnsOriginalResultWhenMessagesEmpty(): void
    {
        $testInfo = $this->makeTestInfo();
        $messenger = new TestMessenger();
        $interceptor = new OutputInterceptor($messenger);
        $testResult = new TestResult($testInfo, Status::Passed);

        $result = $interceptor->runTest($testInfo, static fn() => $testResult);

        Assert::same($result, $testResult);
        Assert::true($result->messages->isEmpty());
    }

    public function runTestAttachesMessagesToResultWhenMessagesPresent(): void
    {
        $testInfo = $this->makeTestInfo();
        $messenger = new TestMessenger();
        $interceptor = new OutputInterceptor($messenger);
        $testResult = new TestResult($testInfo, Status::Passed);

        $result = $interceptor->runTest($testInfo, static function () use ($messenger, $testResult): TestResult {
            $messenger->recordMessage(new Message(
                time: \microtime(true),
                channel: 'stdout',
                level: Level::Info,
                content: 'test output',
            ));
            return $testResult;
        });

        Assert::notSame($result, $testResult);
        Assert::count($result->messages, 1);
        $message = $result->messages->all()[0];
        Assert::same($message->channel, 'stdout');
        Assert::same($message->content, 'test output');
        Assert::same($result->status, Status::Passed);
    }

    public function runTestIsolatesPreScopeMessagesFromResult(): void
    {
        $testInfo = $this->makeTestInfo();
        $messenger = new TestMessenger();
        $messenger->log('stdout', 'leaked from before scope');
        $interceptor = new OutputInterceptor($messenger);
        $testResult = new TestResult($testInfo, Status::Passed);

        $result = $interceptor->runTest($testInfo, static function () use ($messenger, $testResult): TestResult {
            $messenger->recordMessage(new Message(
                time: \microtime(true),
                channel: 'stdout',
                level: Level::Info,
                content: 'in scope',
            ));
            return $testResult;
        });

        Assert::count($result->messages, 1);
        Assert::same($result->messages->all()[0]->content, 'in scope');
    }

    public function runTestPreservesResultStatusWhenAttachingMessages(): void
    {
        $testInfo = $this->makeTestInfo();
        $messenger = new TestMessenger();
        $interceptor = new OutputInterceptor($messenger);
        $testResult = new TestResult($testInfo, Status::Failed, failure: new \Exception('Test failed'));

        $result = $interceptor->runTest($testInfo, static function () use ($messenger, $testResult): TestResult {
            $messenger->recordMessage(new Message(
                time: \microtime(true),
                channel: 'stdout',
                level: Level::Error,
                content: 'error output',
            ));
            return $testResult;
        });

        Assert::same($result->status, Status::Failed);
        Assert::false($result->messages->isEmpty());
    }

    private function makeTestInfo(): TestInfo
    {
        $caseDefinition = new CaseDefinition(
            name: 'TestCase',
            type: 'unit',
            file: Path::create(__FILE__),
            reflection: null,
        );
        $caseInfo = new CaseInfo($caseDefinition, new SuiteIdentity('Core/Pipeline'));
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(SimpleTest::class, 'test'),
        );

        return new TestInfo('test', $caseInfo, $testDefinition);
    }
}

final class SimpleTest
{
    public function test(): void {}
}

final class TestMessenger implements Messenger
{
    /** @var Message[] */
    private array $messages = [];

    public function log(string $channel, string $content, Level $level = Level::Info, array $context = []): void
    {
        $this->messages[] = new Message(
            time: \microtime(true),
            channel: $channel,
            level: $level,
            content: $content,
        );
    }

    public function channel(string $name): Messenger\Channel
    {
        throw new \Exception('Not implemented in test');
    }

    /**
     * Mirrors the real {@see \Testo\Application\Internal\MessengerHub::scope()}: swaps in a fresh,
     * empty child buffer for the duration of the callback so {@see getMessages()} inside observes
     * only what was written within the scope, then restores the parent and discards the child.
     */
    public function scope(\Closure $scope, ?TestIdentity $identity = null): mixed
    {
        $parent = $this->messages;
        $this->messages = [];
        try {
            return $scope($this);
        } finally {
            $this->messages = $parent;
        }
    }

    public function fork(\Closure $fork, bool $holdEvents = false): mixed
    {
        throw new \Exception('Not implemented in test');
    }

    public function getMessages(): MessageLog
    {
        return new MessageLog($this->messages);
    }

    public function recordMessage(Message $message): void
    {
        $this->messages[] = $message;
    }
}
