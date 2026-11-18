<?php

declare(strict_types=1);

namespace Tests\Core\Metadata;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Metadata\MetadataKind;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;
use Testo\Metric\Metric;
use Testo\Test;

#[Test]
#[Covers(MetadataEntry::class)]
final class MetadataEntryTest
{
    public function aBareIntegerBecomesADimensionlessMetric(): void
    {
        $entry = MetadataEntry::number('count', 42);

        Assert::same($entry->name, 'count');
        Assert::same($entry->kind, MetadataKind::Number);
        Assert::instanceOf($entry->value, Metric::class);
        Assert::same($entry->value->value, 42);
        Assert::same($entry->value->unit, Scalar::Number);
    }

    public function aBareFloatBecomesADimensionlessMetric(): void
    {
        $entry = MetadataEntry::number('ratio', 0.5);

        Assert::instanceOf($entry->value, Metric::class);
        Assert::same($entry->value->value, 0.5);
        Assert::same($entry->value->unit, Scalar::Number);
    }

    public function aMetricKeepsItsUnit(): void
    {
        $metric = new Metric(1.5, Time::Milliseconds);

        $entry = MetadataEntry::number('elapsed', $metric);

        Assert::same($entry->kind, MetadataKind::Number);
        Assert::same($entry->value, $metric);
    }

    public function stringFactoriesSetTheirKind(): void
    {
        $text = MetadataEntry::text('note', 'hello');
        $link = MetadataEntry::link('docs', 'https://example.com');
        $image = MetadataEntry::image('chart', '/tmp/chart.png');
        $artifact = MetadataEntry::artifact('log', '/tmp/run.log');

        Assert::same([$text->name, $text->kind, $text->value], ['note', MetadataKind::Text, 'hello']);
        Assert::same([$link->name, $link->kind, $link->value], ['docs', MetadataKind::Link, 'https://example.com']);
        Assert::same([$image->name, $image->kind, $image->value], ['chart', MetadataKind::Image, '/tmp/chart.png']);
        Assert::same([$artifact->name, $artifact->kind, $artifact->value], ['log', MetadataKind::Artifact, '/tmp/run.log']);
    }
}
