<?php

declare(strict_types=1);

namespace Tests\Data\Self;

use Testo\Application\Attribute\Test;
use Testo\Assert;
use Testo\Data\DataProvider;

final class DataZip
{
    public static function numbersProvider(): array
    {
        return [
            [1, 2],
            [3, 4],
            [5, 6],
        ];
    }

    public static function lettersProvider(): iterable
    {
        yield 'ab' => ['a', 'b'];
        yield 'cd' => ['c', 'd'];
        yield 'ef' => ['e', 'f'];
    }

    #[Test]
    #[\Testo\Data\DataZip(
        new DataProvider('numbersProvider'),
        new DataProvider('lettersProvider'),
    )]
    public function sum(int $a, int $b, string $c, string $d): void
    {
        Assert::true(\in_array([$a, $b, $c, $d], [
            [1, 2, 'a', 'b'],
            [3, 4, 'c', 'd'],
            [5, 6, 'e', 'f'],
        ], true));
    }
}
