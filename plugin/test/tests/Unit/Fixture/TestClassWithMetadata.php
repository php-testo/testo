<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Fixture;

use Testo\Test\MetadataType;
use Testo\Test\TestMetadata;

/**
 * Fixture for {@see \Tests\Test\Unit\Internal\TestMetadataInterceptorTest}: methods carrying (and not
 * carrying) {@see TestMetadata}. Excluded from discovery — driven by reflection from the test.
 */
final class TestClassWithMetadata
{
    #[TestMetadata(name: 'score', value: '97.3')]
    #[TestMetadata(name: 'count', value: '42', type: MetadataType::Number)]
    #[TestMetadata(name: 'elapsed', value: '12.5', type: MetadataType::Milliseconds)]
    #[TestMetadata(name: 'memory', value: '2048', type: MetadataType::Bytes)]
    #[TestMetadata(name: 'coverage', value: '87.5', type: MetadataType::Percent)]
    #[TestMetadata(name: 'note', value: 'see chart.png', type: MetadataType::Text)]
    #[TestMetadata(name: 'docs', value: 'docs/index.html', type: MetadataType::Link)]
    #[TestMetadata(name: 'chart', value: 'chart.png', type: MetadataType::Image)]
    #[TestMetadata(name: 'log', value: 'logs/run.log', type: MetadataType::Artifact)]
    public function reportsEveryType(): void {}

    #[TestMetadata(name: 'remote', value: 'https://example.com/chart.png', type: MetadataType::Image)]
    #[TestMetadata(name: 'unix', value: '/var/log/run.log', type: MetadataType::Artifact)]
    #[TestMetadata(name: 'windows', value: 'C:\\logs\\run.log', type: MetadataType::Artifact)]
    public function reportsAbsoluteLocations(): void {}

    #[TestMetadata(name: 'speed', value: 'fast', type: MetadataType::Milliseconds)]
    public function reportsANonNumericNumber(): void {}

    public function noMetadata(): void {}
}
