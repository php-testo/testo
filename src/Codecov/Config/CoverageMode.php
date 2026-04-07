<?php

declare(strict_types=1);

namespace Testo\Codecov\Config;

/**
 * Controls code coverage collection behavior.
 *
 * Set in the container (e.g. from CLI flags) before plugin configuration.
 * If not set, {@see CoverageMode::IfAvailable} is used as default.
 *
 * @api
 */
enum CoverageMode
{
    /**
     * Always collect coverage. Throw if no extension is available.
     */
    case Always;

    /**
     * Collect coverage if extension is available, skip silently if not.
     */
    case IfAvailable;

    /**
     * Skip coverage entirely, zero overhead.
     */
    case Never;
}
