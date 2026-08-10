<?php

declare(strict_types=1);

namespace Testo\Codecov\Exception;

use Testo\Codecov\Config\CoverageLevel;

/**
 * Thrown when branch or path coverage is requested on an XDebug build that corrupts memory while
 * collecting it inside a fiber.
 *
 * XDebug's branch analysis before 3.4.5 crashes the process — with no PHP-level error and no report —
 * when a fiber switch happens under an open coverage window. Testo closes the window around every
 * switch, which is enough from 3.4.5 on; older builds fault regardless, so the run is stopped with
 * this exception instead.
 */
final class BranchCoverageUnsafeInFiber extends \RuntimeException
{
    /**
     * @param non-empty-string $version Loaded XDebug version.
     * @param non-empty-string $required Lowest version that survives this.
     */
    public function __construct(CoverageLevel $level, string $version, string $required)
    {
        parent::__construct(\sprintf(
            'Coverage level `%s` cannot be collected inside a fiber with XDebug %s: the extension '
            . 'crashes the process. Upgrade XDebug to %s or newer, or drop the level to `%s`.',
            $level->name,
            $version,
            $required,
            CoverageLevel::Line->name,
        ));
    }
}
