<?php

declare(strict_types=1);

namespace Tests\Core\Metadata;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Metadata\Metadata;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Test;

#[Test]
#[Covers(Metadata::class)]
final class MetadataTest
{
    public function aNewCollectionIsEmpty(): void
    {
        $metadata = new Metadata();

        Assert::true($metadata->isEmpty());
        Assert::count($metadata, 0);
        Assert::same(\iterator_to_array($metadata), []);
    }

    public function withAppendsEntriesInOrder(): void
    {
        $first = MetadataEntry::text('first', 'a');
        $second = MetadataEntry::number('second', 1);
        $third = MetadataEntry::link('third', 'https://example.com');

        $metadata = (new Metadata([$first]))->with($second, $third);

        Assert::false($metadata->isEmpty());
        Assert::count($metadata, 3);
        Assert::same($metadata->entries, [$first, $second, $third]);
        Assert::same(\iterator_to_array($metadata), [$first, $second, $third]);
    }

    public function withLeavesTheOriginalUntouched(): void
    {
        $original = new Metadata([MetadataEntry::text('first', 'a')]);

        $original->with(MetadataEntry::text('second', 'b'));

        Assert::count($original, 1);
    }

    public function withNothingReturnsTheSameCollection(): void
    {
        $metadata = new Metadata([MetadataEntry::text('first', 'a')]);

        Assert::same($metadata->with(), $metadata);
    }

    public function repeatedNamesAreKeptSideBySide(): void
    {
        $metadata = (new Metadata())
            ->with(MetadataEntry::number('time', 1))
            ->with(MetadataEntry::number('time', 2));

        Assert::count($metadata, 2);
    }

    public function withSpreadOfAKeyedArrayStillYieldsAList(): void
    {
        $entries = ['a' => MetadataEntry::text('a', '1'), 'b' => MetadataEntry::text('b', '2')];

        $metadata = (new Metadata())->with(...$entries);

        Assert::array($metadata->entries)->isList();
    }
}
