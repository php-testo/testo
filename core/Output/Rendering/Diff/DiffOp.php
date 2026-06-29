<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * The kind of a single {@see DiffLine}: a line kept in common, removed from the
 * expected side, or added on the actual side.
 *
 * @internal
 */
enum DiffOp
{
    case Context;
    case Remove;
    case Add;
}
