<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Messenger;

use Internal\Path;
use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\MessengerHub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\Identity\TestIdentity;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Event\Message\MessageReceived;
use Testo\Test;

#[Test]
#[Covers(MessengerHub::class)]
final class MessengerHubTest
{
    public function committedForkMessagesFoldIntoParent(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());
        $hub->log('c', 'a');

        $hub->fork(static function (callable $commit) use ($hub): void {
            $hub->log('c', 'b');
            $commit();
        });

        Assert::same($this->contents($hub->getMessages()), ['a', 'b']);
    }

    public function abandonedForkIsDropped(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());
        $hub->log('c', 'a');

        $hub->fork(static function (callable $commit) use ($hub): void {
            $hub->log('c', 'b'); // no commit → branch is discarded on exit
        });

        Assert::same($this->contents($hub->getMessages()), ['a']);
    }

    /**
     * The retry/repeat pattern: the closure hands the `$commit` callable back out, and the caller
     * decides to keep the branch only after seeing the result — i.e. after `fork()` has returned.
     */
    public function commitCanBeDeferredPastTheClosure(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());
        $hub->log('c', 'a');

        [$commit] = $hub->fork(static function (callable $commit) use ($hub): array {
            $hub->log('c', 'b');
            return [$commit];
        });
        $commit();

        Assert::same($this->contents($hub->getMessages()), ['a', 'b']);
    }

    public function deferredForkLeftUncommittedIsDropped(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());
        $hub->log('c', 'a');

        $hub->fork(static function (callable $commit) use ($hub): array {
            $hub->log('c', 'b');
            return [$commit]; // never invoked → branch dropped
        });

        Assert::same($this->contents($hub->getMessages()), ['a']);
    }

    public function forkReturnsClosureResult(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());

        $result = $hub->fork(static fn(callable $commit): int => 42);

        Assert::same($result, 42);
    }

    /**
     * Model A: events are the global firehose — a dropped fork still announces its messages live,
     * it just never persists them into the scope.
     */
    public function eventsFireForADroppedForkToo(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<string> */
            public array $seen = [];

            public function dispatch(object $event): object
            {
                $event instanceof MessageReceived and $this->seen[] = $event->message->content;
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);

        $hub->fork(static function (callable $commit) use ($hub): void {
            $hub->log('c', 'b');
        });

        Assert::same($dispatcher->seen, ['b']);
        Assert::same($this->contents($hub->getMessages()), []);
    }

    public function holdEventsDefersDispatchUntilCommit(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<string> */
            public array $seen = [];

            public function dispatch(object $event): object
            {
                $event instanceof MessageReceived and $this->seen[] = $event->message->content;
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);

        [$commit] = $hub->fork(static function (callable $commit) use ($hub): array {
            $hub->log('c', 'b');
            return [$commit];
        }, holdEvents: true);

        Assert::same($dispatcher->seen, []);    // held — not dispatched while the fork is open
        $commit();
        Assert::same($dispatcher->seen, ['b']); // released on commit
    }

    public function heldEventsOfADroppedForkAreNeverDispatched(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<string> */
            public array $seen = [];

            public function dispatch(object $event): object
            {
                $event instanceof MessageReceived and $this->seen[] = $event->message->content;
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);

        $hub->fork(static function (callable $commit) use ($hub): array {
            $hub->log('c', 'b');
            return [$commit]; // never committed
        }, holdEvents: true);

        Assert::same($dispatcher->seen, []);
    }

    public function forkIsFiberSafeAcrossSuspension(): void
    {
        $hub = new MessengerHub($this->nullDispatcher());
        $hub->log('c', 'root');

        $fiber = new \Fiber(static function () use ($hub): void {
            $hub->fork(static function (callable $commit) use ($hub): void {
                $hub->log('c', 'before');
                \Fiber::suspend();
                $hub->log('c', 'after');
                $commit();
            });
        });

        $fiber->start();                    // logs 'before' into the fork, then suspends
        $hub->log('c', 'while-suspended');  // parent state restored → lands outside the fork
        $fiber->resume();                   // logs 'after' into the fork, then commits

        # Order is asserted by StateTest; here we only care that nothing leaked or was lost.
        $contents = $this->contents($hub->getMessages());
        \sort($contents);
        Assert::same($contents, ['after', 'before', 'root', 'while-suspended']);
    }

    public function aNestedScopeInheritsTheEnclosingTestIdentity(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<TestIdentity|null> */
            public array $identities = [];

            public function dispatch(object $event): object
            {
                $event instanceof MessageReceived and $this->identities[] = $event->identity;
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);
        $identity = self::identity();

        $hub->scope(static function () use ($hub): void {
            // A plugin isolating output mid-test opens a scope of its own; what is written inside
            // still originates from the running test, so it must stay attributed to that test.
            $hub->scope(static fn() => $hub->log('c', 'inner'));
        }, $identity);

        Assert::same($dispatcher->identities, [$identity]);
    }

    public function aNestedScopeMayStillSetAnIdentityOfItsOwn(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<TestIdentity|null> */
            public array $identities = [];

            public function dispatch(object $event): object
            {
                $event instanceof MessageReceived and $this->identities[] = $event->identity;
                return $event;
            }
        };
        $hub = new MessengerHub($dispatcher);
        $outer = self::identity();
        $inner = $outer->toDataSet(dataProvider: 0, dataSet: 1);

        $hub->scope(static function () use ($hub, $inner): void {
            $hub->scope(static fn() => $hub->log('c', 'inner'), $inner);
        }, $outer);

        // Inheritance is only the default: the per-data-set scope narrows the batch's address.
        Assert::same($dispatcher->identities, [$inner]);
    }

    private static function identity(): TestIdentity
    {
        return (new SuiteIdentity('Application/Unit'))
            ->toCase('Tests\Foo\BarTest', 'test', Path::create('/app/tests/BarTest.php'))
            ->toTest('itWorks');
    }

    private function nullDispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
    }

    /**
     * @return list<string>
     */
    private function contents(MessageLog $log): array
    {
        return \array_map(static fn(Message $m): string => $m->content, $log->all());
    }
}
