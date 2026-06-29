<?php

declare(strict_types=1);

namespace Tests\Spec\Unit;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\MessengerHub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Messenger;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Spec;
use Testo\Spec\Internal\SpecInterceptor;
use Testo\Test;
use Tests\Spec\Unit\Fixture\HeaderedCase;

#[Test]
#[Covers(SpecInterceptor::class)]
final class SpecInterceptorTest
{
    public function runsTheTestOnce(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story'), $messenger);
        $callCount = 0;
        $next = static function (TestInfo $info) use (&$callCount): TestResult {
            $callCount++;
            return new TestResult(info: $info, status: Status::Passed);
        };

        $result = $interceptor->runTest(self::createTestInfo(), $next);

        Assert::same($callCount, 1);
        Assert::same($result->status, Status::Passed);
    }

    public function publishesFragmentToTheChannel(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'As a user I want X.'), $messenger);

        $interceptor->runTest(self::createTestInfo(), self::passing());

        $messages = $messenger->getMessages()->channel(SpecInterceptor::CHANNEL);
        Assert::same(\count($messages), 1);
        Assert::true(\str_contains($messages[0]->content, 'As a user I want X.'));
    }

    public function titleIsNullWithoutAHeaderButRendersTheTestName(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story'), $messenger);

        $interceptor->runTest(self::createTestInfo(), self::passing());

        $message = self::firstMessage($messenger);
        Assert::null($message->context['title']);
        Assert::null($message->context['number']);
        Assert::null($message->context['sectionTitle']);
        Assert::same($message->context['test'], 'testMethod');
        Assert::true(\str_contains($message->content, '### testMethod'));
    }

    public function contextCarriesStructuredFields(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story', tags: ['checkout']), $messenger);

        $interceptor->runTest(self::createTestInfo(), self::passing());

        $context = self::firstMessage($messenger)->context;
        Assert::same($context['story'], 'story');
        Assert::same($context['tags'], ['checkout']);
        Assert::same($context['case'], 'TestCase [test]');
        Assert::true($context['line'] > 0);
    }

    public function readsSectionHeaderFromTheClass(): void
    {
        $context = self::runFixture('withoutHeader');

        Assert::same($context['sectionTitle'], 'Checkout');
        Assert::same($context['sectionNumber'], '5');
    }

    public function readsItemHeaderFromTheMethod(): void
    {
        $context = self::runFixture('withHeader');

        Assert::same($context['title'], 'Tax in total');
        Assert::same($context['number'], '5.1');
    }

    public function methodTitleOverridesTestNameInRenderedContent(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story'), $messenger);

        $interceptor->runTest(self::fixtureInfo('withHeader'), self::passing());

        Assert::true(\str_contains(self::firstMessage($messenger)->content, '5.1 Tax in total'));
    }

    public function rendersTagsLine(): void
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story', tags: ['checkout', 'JIRA-1']), $messenger);

        $interceptor->runTest(self::createTestInfo(), self::passing());

        Assert::true(\str_contains(self::firstMessage($messenger)->content, '`checkout` `JIRA-1`'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function runFixture(string $method): array
    {
        $messenger = self::createMessenger();
        $interceptor = new SpecInterceptor(new Spec(story: 'story'), $messenger);

        $interceptor->runTest(self::fixtureInfo($method), self::passing());

        return self::firstMessage($messenger)->context;
    }

    /**
     * @return \Closure(TestInfo): TestResult
     */
    private static function passing(): \Closure
    {
        return static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed);
    }

    private static function firstMessage(Messenger $messenger): Message
    {
        return $messenger->getMessages()->channel(SpecInterceptor::CHANNEL)[0];
    }

    private static function createMessenger(): Messenger
    {
        return new MessengerHub(new class implements EventDispatcherInterface {
            #[\Override]
            public function dispatch(object $event): object
            {
                return $event;
            }
        });
    }

    private static function createTestInfo(): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, 'createTestInfo');
        $caseInfo = new CaseInfo(definition: new CaseDefinition(name: 'TestCase', type: 'test'));

        return new TestInfo(
            name: 'testMethod',
            caseInfo: $caseInfo,
            testDefinition: new TestDefinition(reflection: $reflection),
        );
    }

    private static function fixtureInfo(string $method): TestInfo
    {
        $class = new \ReflectionClass(HeaderedCase::class);
        $caseInfo = new CaseInfo(definition: new CaseDefinition(name: HeaderedCase::class, type: 'test', reflection: $class));

        return new TestInfo(
            name: $method,
            caseInfo: $caseInfo,
            testDefinition: new TestDefinition(reflection: $class->getMethod($method)),
        );
    }
}
