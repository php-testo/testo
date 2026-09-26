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
 * @see Assert::bool()
 */
#[Test]
#[Covers(Assert::class, 'bool')]
final class AssertBool
{
    #[DataSet([true], 'true')]
    #[DataSet([false], 'false')]
    public function boolPasses(bool $value): void
    {
        Assert::bool($value);
    }

    #[DataSet([0, 'Failed assertion that `0` is bool: got 0.'], 'int zero')]
    #[DataSet([1, 'Failed assertion that `1` is bool: got 1.'], 'int one')]
    #[DataSet(['true', 'Failed assertion that `"true"` is bool: got "true".'], 'string true')]
    #[DataSet([null, 'Failed assertion that `null` is bool: got null.'], 'null')]
    public function nonBoolFails(mixed $value, string $message): never
    {
        Expect::exception(AssertionException::class)
            ->withMessage($message);
        Assert::bool($value);
    }
}
