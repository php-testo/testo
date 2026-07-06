<?php

declare(strict_types=1);

namespace Tests\Application\Unit\Messenger;

use Psr\EventDispatcher\EventDispatcherInterface;
use Testo\Application\Internal\Messenger\State;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Event\Message\MessageReceived;
use Testo\Test;

#[Test]
#[Covers(State::class)]
final class StateTest
{
    public function emptyStateHasNoMessages(): void
    {
        Assert::same((new State(self::dispatcher()))->getMessages(), []);
    }

    public function pushPreservesInsertionOrder(): void
    {
        $state = new State(self::dispatcher());
        $state->record(self::message(1.0, 'a'));
        $state->record(self::message(2.0, 'b'));

        Assert::same($this->contents($state), ['a', 'b']);
    }

    public function forkViewCombinesParentAndOwnMessages(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));

        Assert::same($this->contents($fork), ['a', 'b']);
    }

    public function forkLeavesParentUntouchedUntilCommit(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));

        Assert::same($this->contents($root), ['a']);
    }

    public function readingForkDoesNotMutateParent(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));

        # Reading the merged view repeatedly must stay read-only on the real parent.
        $fork->getMessages();
        $fork->getMessages();

        Assert::same($this->contents($root), ['a']);
        Assert::same($this->contents($fork), ['a', 'b']);
    }

    public function commitMergesForkIntoParent(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));

        $fork->commit();

        Assert::same($this->contents($root), ['a', 'b']);
    }

    public function commitIntoEmptyParentTakesForkMessages(): void
    {
        $root = new State(self::dispatcher());
        $fork = $root->fork();
        $fork->record(self::message(1.0, 'a'));

        $fork->commit();

        Assert::same($this->contents($root), ['a']);
    }

    public function abandonedForkDoesNotAffectParent(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));

        # No commit: the fork is simply dropped.

        Assert::same($this->contents($root), ['a']);
    }

    public function nestedForksCommitUpTheChain(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $inner = $root->fork();
        $inner->record(self::message(2.0, 'b'));
        $leaf = $inner->fork();
        $leaf->record(self::message(3.0, 'c'));

        Assert::same($this->contents($leaf), ['a', 'b', 'c']);

        $leaf->commit();
        Assert::same($this->contents($inner), ['a', 'b', 'c']);
        Assert::same($this->contents($root), ['a']);

        $inner->commit();
        Assert::same($this->contents($root), ['a', 'b', 'c']);
    }

    public function commitAppendsChronologicalMessages(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(1.0, 'a'));
        $fork = $root->fork();
        $fork->record(self::message(2.0, 'b'));
        $fork->record(self::message(3.0, 'c'));

        $fork->commit();

        Assert::same($this->contents($root), ['a', 'b', 'c']);
    }

    public function commitSortsOutOfOrderMessagesByTime(): void
    {
        $root = new State(self::dispatcher());
        $root->record(self::message(100.0, 'late'));
        $fork = $root->fork();
        $fork->record(self::message(50.0, 'early'));

        $fork->commit();

        Assert::same($this->contents($root), ['early', 'late']);
    }

    public function heldEventsFromNestedHoldForkAreReleasedByOuterCommit(): void
    {
        /** @var list<string> $dispatched */
        $dispatched = [];
        $dispatcher = new class($dispatched) implements EventDispatcherInterface {
            /** @param list<string> $dispatched */
            public function __construct(private array &$dispatched) {}

            #[\Override]
            public function dispatch(object $event): object
            {
                if ($event instanceof MessageReceived) {
                    $this->dispatched[] = $event->message->content;
                }
                return $event;
            }
        };

        $root = new State($dispatcher);
        $parent = $root->fork(holdEvents: true);
        $child = $parent->fork(holdEvents: true);

        // Record out-of-order by time so the usort in absorbEvents makes a visible difference.
        $child->record(self::message(2.0, 'late'));
        $child->record(self::message(1.0, 'early'));

        $child->commit();
        Assert::same($dispatched, []);               // still held by parent

        $parent->commit();
        Assert::same($dispatched, ['early', 'late']); // released in time order
    }

    public function destroyClearsBuffer(): void
    {
        $state = new State(self::dispatcher());
        $state->record(self::message(1.0, 'a'));

        $state->destroy();

        Assert::same($state->getMessages(), []);
    }

    /**
     * @return list<string>
     */
    private function contents(State $state): array
    {
        return \array_map(static fn(Message $m): string => $m->content, $state->getMessages());
    }

    private static function dispatcher(): EventDispatcherInterface
    {
        return new class() implements EventDispatcherInterface {
            #[\Override]
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
    }

    private static function message(float $time, string $content): Message
    {
        return new Message($time, 'c', Level::Info, $content);
    }
}
