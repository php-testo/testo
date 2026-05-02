<?php

declare(strict_types=1);

namespace Tests\Assert\Self;

use Testo\Assert;
use Testo\Assert\State\AssertException;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Expect;
use Testo\Test;

/**
 * @see Assert::count()
 */
final readonly class AssertCount
{
    #[Test]
    public function countableWithCorrectCount(): void
    {
        Assert::count(new \ArrayObject([1, 2, 3]), 3);
    }

    #[Test]
    public function countableWithWrongCount(): void
    {
        Expect::exception(AssertException::class);
        Assert::count(new \ArrayObject([1, 2, 3]), 5);
    }

    #[Test]
    public function countableEmpty(): void
    {
        Assert::count(new \ArrayObject(), 0);
    }

    #[Test]
    public function arrayWithCorrectCount(): void
    {
        Assert::count([1, 2, 3], 3);
    }

    #[Test]
    public function arrayWithWrongCount(): void
    {
        Expect::exception(AssertionException::class);
        Assert::count([1, 2, 3], 1);
    }

    #[Test]
    public function emptyArray(): void
    {
        Assert::count([], 0);
    }

    #[Test]
    public function generatorWithCorrectCount(): void
    {
        Assert::count($this->generateItems(3), 3);
    }

    #[Test]
    public function generatorWithWrongCount(): void
    {
        Expect::exception(AssertionException::class);
        Assert::count($this->generateItems(3), 5);
    }

    /**
     * @return \Generator<int, int>
     */
    private function generateItems(int $count): \Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield $i;
        }
    }
}
