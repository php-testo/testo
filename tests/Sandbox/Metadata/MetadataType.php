<?php

declare(strict_types=1);

namespace Tests\Sandbox\Metadata;

/**
 * TeamCity `testMetadata` value types.
 *
 * The set a CI server and an IDE know how to render: a number it charts across builds, plain text, a
 * clickable link, an inline image, and a downloadable artifact.
 *
 * @link https://www.jetbrains.com/help/teamcity/reporting-test-metadata.html
 */
enum MetadataType: string
{
    case Number = 'number';
    case Text = 'text';
    case Link = 'link';
    case Image = 'image';
    case Artifact = 'artifact';
}
