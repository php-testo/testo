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
    #[TestMetadata(name: 'score', value: '97.3', type: MetadataType::Number)]
    #[TestMetadata(name: 'chart', value: 'chart.png', type: MetadataType::Image)]
    public function reports(): void {}

    public function noMetadata(): void {}
}
