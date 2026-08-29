<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::blank()
 * @see Assert::notBlank()
 */
#[Test]
#[Covers(Assert::class, 'blank')]
#[Covers(Assert::class, 'notBlank')]
final class AssertBlank
{
    public function checkBlankData(): void
    {
        Assert::blank([]);
        Assert::blank("");
        Assert::blank(null);
        Assert::blank(new \ArrayIterator());
    }

    #[DataSet([0], 'integer zero')]
    #[DataSet(['0'], 'string zero')]
    #[DataSet([false], 'boolean false')]
    public function checkNotBlankFails(mixed $value): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::blank($value, 'my wonderful message');
    }

    public function checkNotBlankData(): void
    {
        Assert::notBlank([1]);
        Assert::notBlank('a');
        Assert::notBlank(new \ArrayIterator([1]));
        // unlike empty(), these represent valid data and are considered non-blank
        Assert::notBlank(0);
        Assert::notBlank('0');
        Assert::notBlank(false);
    }

    #[DataSet([[]], 'empty array')]
    #[DataSet([''], 'empty string')]
    #[DataSet([null], 'null')]
    public function checkBlankFailsNotBlank(mixed $value): never
    {
        Expect::exception(AssertionException::class)
            ->withMessageContaining('my wonderful message');
        Assert::notBlank($value, 'my wonderful message');
    }
}
