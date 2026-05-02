<?php

declare(strict_types=1);

namespace Testo\Assert\Exception;

use Testo\Assert\AssertPlugin;

final class StateNotFound extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(\sprintf(
            <<<TEXT
                No current test state found to set an expectation.
                Make sure that the `%s` plugin is installed.
                TEXT,
            AssertPlugin::class,
        ));
    }
}
