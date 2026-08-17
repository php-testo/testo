<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

/**
 * What a {@see Destination} receives: the rendered page, or the document on its own.
 *
 * @internal
 * @psalm-internal Testo\Output\Html
 */
enum ReportKind
{
    case Html;
    case Data;
}
