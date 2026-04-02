<?php

declare(strict_types=1);

namespace Testo\Codecov\Exception;

/**
 * Thrown when no supported coverage driver extension is available.
 */
final class CoverageDriverNotAvailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No code coverage driver available. Install the PCOV or XDebug extension.',
        );
    }
}
