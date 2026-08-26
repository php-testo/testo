<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

use Testo\Assert;
use Testo\Filter\Group;
use Testo\Test;

/**
 * Playground for the `testMetadata` TeamCity protocol.
 *
 * Every test declares one or more {@see TestMetadata} values that {@see TestMetadataInterceptor} turns
 * into `##teamcity[testMetadata …]` messages. Run the `sandbox` suite against an IDE or CI server that
 * understands the protocol and watch each type render: numbers as charts and a table, text and links
 * inline, images and artifacts as attachments, sourced from both local files and URLs.
 *
 * The leading `echo` in each test is deliberate: it makes the test emit output, which opens its
 * `testStarted` node before the interceptor writes the metadata into it.
 */
#[Test]
#[Group('sandbox')]
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
        echo "Reporting a table of numeric metrics.\n";
        Assert::true(true);
    }

    /**
     * A single number is the simplest chartable metric.
     */
    #[TestMetadata(name: 'score', value: '97.3', type: MetadataType::Number)]
    public function singleNumber(): void
    {
        echo "Reporting one number.\n";
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
        echo "Reporting numbers with measurement dimensions.\n";
        Assert::true(true);
    }

    #[TestMetadata(name: 'summary', value: 'Processed 5 batches, 0 retries, warm cache.', type: MetadataType::Text)]
    #[TestMetadata(name: 'commit', value: 'feature/sandbox-test-metadata @ 64f100e4', type: MetadataType::Text)]
    public function text(): void
    {
        echo "Reporting free-form text metadata.\n";
        Assert::true(true);
    }

    #[TestMetadata(name: 'build log', value: 'https://php-testo.github.io/', type: MetadataType::Link)]
    #[TestMetadata(name: 'docs', value: 'https://php-testo.github.io/llms.txt', type: MetadataType::Link)]
    public function links(): void
    {
        echo "Reporting clickable links.\n";
        Assert::true(true);
    }

    /**
     * An image referenced by URL — the value is emitted verbatim.
     */
    #[TestMetadata(
        name: 'remote chart',
        value: 'https://raw.githubusercontent.com/php-testo/testo/1.x/resources/logo.png',
        type: MetadataType::Image,
    )]
    public function imageFromUrl(): void
    {
        echo "Reporting an image by URL.\n";
        Assert::true(true);
    }

    /**
     * An image shipped next to the test — the relative path resolves against this file's directory.
     */
    #[TestMetadata(name: 'local chart', value: 'resources/chart.png', type: MetadataType::Image)]
    public function imageFromLocalFile(): void
    {
        echo "Reporting an image from a local file.\n";
        Assert::true(true);
    }

    /**
     * A downloadable artifact from a local file — a CSV of raw measurements.
     */
    #[TestMetadata(name: 'measurements.csv', value: 'resources/report.csv', type: MetadataType::Artifact)]
    public function artifactFromLocalFile(): void
    {
        echo "Reporting an artifact from a local file.\n";
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
        echo "Reporting an artifact by URL.\n";
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
        echo "Reporting several metadata types from one test.\n";
        Assert::true(true);
    }
}
