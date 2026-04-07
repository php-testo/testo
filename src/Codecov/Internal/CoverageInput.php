<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;
use Testo\Codecov\Config\CoverageMode;

/**
 * CLI input for coverage configuration.
 *
 * @internal
 * @psalm-internal Testo\Codecov
 */
#[InflectableConfig]
final class CoverageInput
{
    #[InputOption('coverage')]
    public bool $coverage = false;

    #[InputOption('no-coverage')]
    public bool $noCoverage = false;

    public function resolveMode(): ?CoverageMode
    {
        return match (true) {
            $this->coverage => CoverageMode::Always,
            $this->noCoverage => CoverageMode::Never,
            default => null,
        };
    }
}
