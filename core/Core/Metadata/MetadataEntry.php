<?php

declare(strict_types=1);

namespace Testo\Core\Metadata;

use Testo\Core\Metric\Scalar;
use Testo\Metric\Metric;
use Testo\Metric\Unit;

/**
 * One named value a test reports beside its status: a measurement, a note, a link or a file.
 *
 * A number carries its {@see Metric}, so each reporter renders the unit its own way; every other kind
 * carries a string.
 *
 * @api
 */
final readonly class MetadataEntry
{
    /**
     * @param non-empty-string $name Dot-separated segments; a reporter may group entries by them.
     * @param Metric<Unit>|string $value A {@see Metric} exactly when `$kind` is {@see MetadataKind::Number}.
     */
    private function __construct(
        public string $name,
        public MetadataKind $kind,
        public Metric|string $value,
    ) {}

    /**
     * @param non-empty-string $name
     * @param Metric<Unit>|int|float $value A bare number is dimensionless.
     */
    public static function number(string $name, Metric|int|float $value): self
    {
        return new self(
            $name,
            MetadataKind::Number,
            $value instanceof Metric ? $value : new Metric($value, Scalar::Number),
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function text(string $name, string $value): self
    {
        return new self($name, MetadataKind::Text, $value);
    }

    /**
     * @param non-empty-string $name
     */
    public static function link(string $name, string $url): self
    {
        return new self($name, MetadataKind::Link, $url);
    }

    /**
     * @param non-empty-string $name
     * @param string $location An absolute path or a URL.
     */
    public static function image(string $name, string $location): self
    {
        return new self($name, MetadataKind::Image, $location);
    }

    /**
     * @param non-empty-string $name
     * @param string $location An absolute path or a URL.
     */
    public static function artifact(string $name, string $location): self
    {
        return new self($name, MetadataKind::Artifact, $location);
    }
}
