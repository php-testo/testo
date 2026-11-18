<?php

declare(strict_types=1);

namespace Testo\Test;

/**
 * What a {@see TestMetadata} value is: a number, dimensionless or in milliseconds, bytes or percent;
 * plain text; a link; an image; or an artifact to keep with the run.
 *
 * @api
 */
enum MetadataType: string
{
    case Number = 'number';
    case Milliseconds = 'ms';
    case Bytes = 'bytes';
    case Percent = 'percent';
    case Text = 'text';
    case Link = 'link';
    case Image = 'image';
    case Artifact = 'artifact';
}
