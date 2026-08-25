<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

/**
 * Attaches one `testMetadata` value to a test, emitted by {@see TestMetadataInterceptor}.
 *
 * Repeatable — stack several to attach a chart's worth of numbers, an image and its source link, and
 * so on. For {@see MetadataType::Image} and {@see MetadataType::Artifact} a relative `$value` is
 * resolved against the directory of the test file, so a fixture shipped next to the test is found;
 * a URL or an absolute path is emitted verbatim.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class TestMetadata
{
    public function __construct(
        public string $name,
        public string $value,
        public MetadataType $type = MetadataType::Number,
    ) {}
}
