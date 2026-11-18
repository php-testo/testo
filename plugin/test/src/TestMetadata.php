<?php

declare(strict_types=1);

namespace Testo\Test;

/**
 * Attaches one metadata value to a test's result, rendered by every reporter.
 *
 * Repeatable: stack several to attach a chart's worth of numbers, an image and its source link, and so
 * on. A numeric type requires a numeric `$value`. For {@see MetadataType::Image} and
 * {@see MetadataType::Artifact} a relative `$value` resolves against the directory of the test file, so a
 * fixture shipped next to the test is found; a URL or an absolute path is kept as is.
 *
 * @see \Testo\Core\Metadata\Metadata
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class TestMetadata
{
    /**
     * @param non-empty-string $name
     */
    public function __construct(
        public string $name,
        public string $value,
        public MetadataType $type = MetadataType::Number,
    ) {}
}
