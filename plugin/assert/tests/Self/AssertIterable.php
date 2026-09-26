<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\Internal\Assertion\AssertIterable as AssertIterableImpl;
use Testo\Assert\Internal\Assertion\Traits\IterableTrait;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::iterable()
 */
#[Test]
#[Covers(Assert::class, 'iterable')]
#[Covers(AssertIterableImpl::class)]
#[Covers(IterableTrait::class)]
final class AssertIterable
{
    public function checkIterableType(): void
    {
        // This assertion checks incoming data type
        Assert::iterable(new \ArrayIterator([1, 2, 3]));
        Assert::iterable([]);
    }

    public function notEmpty(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->notEmpty();
        Assert::iterable([1])->notEmpty();

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([])->notEmpty('my wonderful message');
    }

    public function contains(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->contains(3);
        Assert::iterable([1, 2, 3])->contains(3);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([1, 2, 3])->contains(4, 'my wonderful message');
    }

    public function notContains(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->notContains(4);
        Assert::iterable([1, 2, 3])->notContains('3'); // strict comparison: string '3' is absent

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([1, 2, 3])->notContains(2, 'my wonderful message');
    }

    public function sameSizeAs(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->sameSizeAs(new \ArrayIterator(['a', 'b', 'c']));
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->sameSizeAs(['a', 'b', 'c']);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([1, 2, 3])->sameSizeAs(['a', 'b'], 'my wonderful message');
    }

    public function every(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->every(static fn($value) => \is_int($value));

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([1, 2, 'testo'])->every(static fn($value) => \is_int($value), 'my wonderful message');
    }

    public function allOf(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->allOf('integer');
        Assert::iterable(['a', 'b', 'c'])->allOf('string');

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([true, false, 'true'])->allOf('bool', 'my wonderful message');
    }

    public function allInstanceOf(): never
    {
        Assert::iterable(new \ArrayIterator([new \ArrayObject(), new \ArrayIterator()]))->allInstanceOf(\Countable::class);
        Assert::iterable([new \DateTimeImmutable(), new \DateTime()])->allInstanceOf(\DateTimeInterface::class);
        Assert::iterable([])->allInstanceOf(\stdClass::class);

        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::iterable([new \stdClass(), 'stdClass'])->allInstanceOf(\stdClass::class, 'my wonderful message');
    }

    public function allInstanceOfUnknownClassThrowsOnEmptyIterable(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('Tests\Assert\Self\MissingClass');
        Assert::iterable([])->allInstanceOf('Tests\Assert\Self\MissingClass');
    }

    public function allInstanceOfUnknownClassRecordsNoAssertion(): void
    {
        $iterable = Assert::iterable([new \stdClass()]);
        $history = StaticState::current()?->history ?? [];
        $head = \end($history);
        Assert::instanceOf($head, AssertionComposite::class);

        # A try/catch rather than Expect: the records are only readable after the throw.
        try {
            $iterable->allInstanceOf('Tests\Assert\Self\MissingClass');
        } catch (\InvalidArgumentException) {
        }

        Assert::same($head->getRecords(), []);
    }

    public function hasCount(): never
    {
        Assert::iterable(new \ArrayIterator([1, 2, 3]))->hasCount(3);
        Assert::iterable([1, 2, 3])->hasCount(3);

        Expect::exception(AssertionException::class);
        Assert::iterable([1, 2, 3])->hasCount(2);
    }
}
