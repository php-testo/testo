<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Spec\Internal\SpecEntry;
use Testo\Spec\Internal\SpecNumberer;
use Testo\Test;

#[Test]
#[Covers(SpecNumberer::class)]
#[Covers(SpecEntry::class)]
final class SpecNumbererTest
{
    public function ordersSectionsByNumberNaturally(): void
    {
        $model = SpecNumberer::build([
            self::entry('Checkout', 'a', line: 10, sectionNumber: '10'),
            self::entry('Auth', 'b', line: 10, sectionNumber: '2'),
        ]);

        Assert::same(\array_map(static fn(array $s): string => $s['number'], $model['sections']), ['2', '10']);
    }

    public function autoNumbersItemsBySourceLine(): void
    {
        $model = SpecNumberer::build([
            self::entry('Checkout', 'second', line: 30, sectionNumber: '5'),
            self::entry('Checkout', 'first', line: 10, sectionNumber: '5'),
        ]);

        $items = $model['sections'][0]['items'];
        Assert::same($items[0]['number'], '5.1');
        Assert::same($items[0]['title'], 'first');
        Assert::same($items[1]['number'], '5.2');
        Assert::same($items[1]['title'], 'second');
    }

    public function keepsManualItemNumber(): void
    {
        $model = SpecNumberer::build([
            self::entry('Checkout', 'pinned', line: 10, sectionNumber: '5', number: '5.9'),
        ]);

        Assert::same($model['sections'][0]['items'][0]['number'], '5.9');
    }

    public function disambiguatesCollidingNumbersInDocumentOrder(): void
    {
        $model = SpecNumberer::build([
            self::entry('CheckoutA', 'a', line: 10, sectionNumber: '5', sectionTitle: 'Checkout A'),
            self::entry('CheckoutB', 'b', line: 10, sectionNumber: '5', sectionTitle: 'Checkout B'),
        ]);

        Assert::same($model['sections'][0]['items'][0]['number'], '5.1 (1)');
        Assert::same($model['sections'][1]['items'][0]['number'], '5.1 (2)');
    }

    public function unnumberedSectionsGoToExtra(): void
    {
        $model = SpecNumberer::build([
            self::entry('Numbered', 'a', line: 10, sectionNumber: '1'),
            self::entry('Loose', 'b', line: 10, title: 'A note'),
        ]);

        Assert::same(\count($model['sections']), 1);
        Assert::same(\count($model['extra']), 1);
        Assert::same($model['extra'][0]['items'][0]['title'], 'A note');
    }

    public function fallsBackToCaseNameForSectionTitle(): void
    {
        $model = SpecNumberer::build([
            self::entry('CheckoutCase', 'a', line: 10, sectionNumber: '5'),
        ]);

        Assert::same($model['sections'][0]['title'], 'CheckoutCase');
    }

    public function compareSectionsPutsUnnumberedLast(): void
    {
        Assert::true(SpecNumberer::compareSections('5', null) < 0);
        Assert::true(SpecNumberer::compareSections(null, '5') > 0);
        Assert::same(SpecNumberer::compareSections(null, null), 0);
        Assert::true(SpecNumberer::compareSections('2', '10') < 0);
    }

    public function orderKeysSortsByEffectiveNumber(): void
    {
        $order = SpecNumberer::orderKeys([
            'late' => ['number' => null, 'line' => 30],
            'early' => ['number' => null, 'line' => 10],
        ], '5');

        Assert::same($order, ['early', 'late']);
    }

    /**
     * @param list<non-empty-string> $tags
     */
    private static function entry(
        string $case,
        string $test,
        int $line,
        ?string $sectionNumber = null,
        ?string $sectionTitle = null,
        ?string $number = null,
        ?string $title = null,
        array $tags = [],
    ): SpecEntry {
        return new SpecEntry(
            case: $case,
            test: $test,
            title: $title,
            number: $number,
            sectionTitle: $sectionTitle,
            sectionNumber: $sectionNumber,
            line: $line,
            story: "Story of {$test}.",
            tags: $tags,
        );
    }
}
