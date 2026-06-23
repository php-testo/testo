<?php

declare(strict_types=1);

namespace Tests\Spec\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Spec\SpecHeader;
use Testo\Test;

#[Test]
#[Covers(SpecHeader::class)]
final class SpecHeaderTest
{
    public function titleIsStored(): void
    {
        $header = new SpecHeader(title: 'Checkout');

        Assert::same($header->title, 'Checkout');
    }

    public function numberDefaultsToNull(): void
    {
        $header = new SpecHeader(title: 'Checkout');

        Assert::null($header->number);
    }

    public function stringNumberIsKept(): void
    {
        $header = new SpecHeader(title: 'Tax', number: '5.1');

        Assert::same($header->number, '5.1');
    }

    public function intNumberIsCastToString(): void
    {
        $header = new SpecHeader(title: 'Checkout', number: 5);

        Assert::same($header->number, '5');
    }

    public function numberOnlyHeaderIsAllowed(): void
    {
        $header = new SpecHeader(number: '5');

        Assert::null($header->title);
        Assert::same($header->number, '5');
    }

    public function blankTitleFails(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Spec header title must not be empty when provided.');

        new SpecHeader(title: '  ');
    }

    public function emptyHeaderFails(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Spec header requires a title or a number.');

        new SpecHeader();
    }

    /**
     * @param string $number A provided number must carry an actual value.
     */
    #[DataSet([''], 'empty')]
    #[DataSet(['  '], 'whitespace')]
    public function blankNumberFails(string $number): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessage('Spec header number must not be empty when provided.');

        new SpecHeader(title: 'Checkout', number: $number);
    }
}
