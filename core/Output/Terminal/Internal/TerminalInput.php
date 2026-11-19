<?php

declare(strict_types=1);

namespace Testo\Output\Terminal\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;

/**
 * CLI input for the terminal reporter.
 *
 * @internal
 * @psalm-internal Testo\Output\Terminal
 */
#[InflectableConfig]
final class TerminalInput
{
    /**
     * `--no-progress` — print nothing per suite, case or test while the run goes; the failures and the
     * final report still come out at the end.
     */
    #[InputOption('no-progress')]
    public bool $noProgress = false;
}
