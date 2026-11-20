<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

use Testo\Assert;
use Testo\Filter\Group;
use Testo\Test;
use Testo\Test\MetadataType;
use Testo\Test\TestMetadata;

/**
 * Playground for test metadata.
 *
 * Every test declares one or more {@see TestMetadata} values that the test plugin's interceptor adds to
 * the test's result, and every reporter renders them: TeamCity as `##teamcity[testMetadata …]` messages,
 * JUnit as `<property>` elements. Run the `sandbox` suite against an IDE or CI server that understands
 * the TeamCity protocol and watch each type render: numbers as charts and a table, text and links inline,
 * images and artifacts as attachments, sourced from both local files and URLs.
 */
#[Test]
#[Group('sandbox', 'metadata')]
final class MetadataShowcaseTest
{
    /**
     * Several numeric keys — a CI server charts each across builds and lists them together as a table
     * on the test's page.
     */
    #[TestMetadata(name: 'throughput (ops/s)', value: '1342', type: MetadataType::Number)]
    #[TestMetadata(name: 'latency (ms)', value: '0.74', type: MetadataType::Number)]
    #[TestMetadata(name: 'peak memory (MB)', value: '18.5', type: MetadataType::Number)]
    #[TestMetadata(name: 'allocations', value: '20483', type: MetadataType::Number)]
    public function numbers(): void
    {
        Assert::true(true);
    }

    /**
     * A single number is the simplest chartable metric.
     */
    #[TestMetadata(name: 'score', value: '97.3', type: MetadataType::Number)]
    public function singleNumber(): void
    {
        Assert::true(true);
    }

    /**
     * Dimensioned numbers — TeamCity formats the graph axis by the type, so the value carries no unit.
     */
    #[TestMetadata(name: 'duration', value: '434.5', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'peak memory', value: '1048576', type: MetadataType::Bytes)]
    #[TestMetadata(name: 'coverage', value: '91.2', type: MetadataType::Percent)]
    public function dimensionedNumbers(): void
    {
        Assert::true(true);
    }

    /**
     * A three-level table (`<prefix>.<row>.<column>`, no column group) whose every value shares one type
     * — a grid a consumer can plot as a graph: rows are the x-axis (concurrency), columns are the series
     * (backends), and the milliseconds are the y-axis.
     */
    #[TestMetadata(name: 'response time.redis.1', value: '0.4', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.1', value: '0.9', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.redis.2', value: '0.5', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.2', value: '1.1', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.redis.4', value: '0.7', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.4', value: '1.6', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.redis.8', value: '1.1', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.8', value: '2.7', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.redis.16', value: '2.0', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.16', value: '5.1', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.redis.32', value: '3.8', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'response time.postgres.32', value: '9.4', type: MetadataType::Milliseconds)]
    public function graphTable(): void
    {
        Assert::true(true);
    }

    #[TestMetadata(name: 'summary', value: 'Processed 5 batches, 0 retries, warm cache.', type: MetadataType::Text)]
    #[TestMetadata(name: 'commit', value: 'feature/sandbox-test-metadata @ 64f100e4', type: MetadataType::Text)]
    public function text(): void
    {
        Assert::true(true);
    }

    #[TestMetadata(name: 'build log', value: 'https://php-testo.github.io/', type: MetadataType::Link)]
    #[TestMetadata(name: 'docs', value: 'https://php-testo.github.io/llms.txt', type: MetadataType::Link)]
    public function links(): void
    {
        Assert::true(true);
    }

    /**
     * An image referenced by URL — the value is emitted verbatim.
     */
    #[TestMetadata(
        name: 'remote image',
        value: 'https://github.com/php-testo/.github/blob/1.x/resources/logo-full.svg?raw=true',
        type: MetadataType::Image,
    )]
    public function imageFromUrl(): void
    {
        Assert::true(true);
    }

    /**
     * An image shipped next to the test — the relative path resolves against this file's directory.
     */
    #[TestMetadata(name: 'local image', value: 'resources/chart.png', type: MetadataType::Image)]
    public function imageFromLocalFile(): void
    {
        Assert::true(true);
    }

    /**
     * A downloadable artifact from a local file — a CSV of raw measurements.
     */
    #[TestMetadata(name: 'measurements.csv', value: 'resources/report.csv', type: MetadataType::Artifact)]
    public function artifactFromLocalFile(): void
    {
        Assert::true(true);
    }

    /**
     * A downloadable artifact from a URL.
     */
    #[TestMetadata(
        name: 'remote artifact',
        value: 'https://php-testo.github.io/llms-full.txt',
        type: MetadataType::Artifact,
    )]
    public function artifactFromUrl(): void
    {
        Assert::true(true);
    }

    /**
     * A single test can mix types: a headline number, a note, an image and its source link.
     */
    #[TestMetadata(name: 'coverage (%)', value: '86.4', type: MetadataType::Number)]
    #[TestMetadata(name: 'note', value: 'Coverage rendered from the local chart below.', type: MetadataType::Text)]
    #[TestMetadata(name: 'coverage chart', value: 'resources/chart.png', type: MetadataType::Image)]
    #[TestMetadata(name: 'chart source', value: 'https://php-testo.github.io/', type: MetadataType::Link)]
    public function mixedTypes(): void
    {
        Assert::true(true);
    }
}
