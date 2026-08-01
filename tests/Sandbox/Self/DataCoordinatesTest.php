<?php

declare(strict_types=1);

namespace Tests\Sandbox\Self;

use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Sandbox for the two coordinates that address a data set: the index of the provider it came from, and
 * its index within that provider.
 *
 * Every data set here is yielded under the **same key** on purpose, so a key cannot tell any two of
 * them apart — only the indices can. Each carries a label naming the coordinates it is supposed to sit
 * at, so a `--filter` run says out loud which one it actually reached:
 *
 * ```
 * php vendor/bin/testo --suite=sandbox --filter='DataCoordinatesTest::coordinates:0:1'
 * ```
 */
#[Test]
#[Group('sandbox')]
final class DataCoordinatesTest
{
    /**
     * Provider #0. Three data sets, all keyed `9`.
     */
    public static function first(): iterable
    {
        yield 9 => ['provider-0 / data-set-0'];
        yield 9 => ['provider-0 / data-set-1'];
        yield 9 => ['provider-0 / data-set-2'];
    }

    /**
     * Provider #1. Two more, keyed `9` again — so the key repeats across providers as well.
     */
    public static function second(): iterable
    {
        yield 9 => ['provider-1 / data-set-0'];
        yield 9 => ['provider-1 / data-set-1'];
    }

    #[DataProvider([self::class, 'first'])]
    #[DataProvider([self::class, 'second'])]
    public function coordinates(string $label): void
    {
        echo "reached {$label}\n";

        Assert::true(\str_contains($label, 'data-set-'), 'the label names the coordinates it sits at');
    }
}
