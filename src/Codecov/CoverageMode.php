<?php

declare(strict_types=1);

namespace Testo\Codecov;

/**
 * Controls code coverage collection behavior.
 *
 * Set in the container (e.g. from CLI flags) before plugin configuration.
 * If not set, {@see CoverageMode::Available} is used as default.
 *
 * @api
 */
enum CoverageMode
{
    /**
     * Coverage required. Throw if no extension is available.
     */
    case Required;

    /**
     * Use coverage if extension is available, skip silently if not.
     */
    case Available;

    /**
     * Skip coverage entirely, zero overhead.
     */
    case Disabled;
}
