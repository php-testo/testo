<?php

declare(strict_types=1);

namespace Testo\Core\Metadata;

/**
 * What a {@see MetadataEntry} holds, and so how a reporter presents it.
 *
 * @api
 */
enum MetadataKind: string
{
    /**
     * A measured {@see \Testo\Metric\Metric}, chartable across runs.
     */
    case Number = 'number';
    case Text = 'text';

    /**
     * A URL.
     */
    case Link = 'link';

    /**
     * A path or URL to a picture.
     */
    case Image = 'image';

    /**
     * A path or URL to a file to keep with the run.
     */
    case Artifact = 'artifact';
}
