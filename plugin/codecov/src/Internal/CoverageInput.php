<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Application\Config\Internal\Attribute\InflectableConfig;
use Testo\Application\Config\Internal\Attribute\InputOption;
use Testo\Codecov\Config\CoverageLevel;
use Testo\Codecov\Config\CoverageMode;
use Testo\Codecov\Report\CloverReport;
use Testo\Codecov\Report\CoberturaReport;
use Testo\Codecov\Report\CoverageReport;
use Testo\Codecov\Report\PhpUnitXmlReport;

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

    /** Target file for a Clover XML report (`--coverage-clover=<file>`). */
    #[InputOption('coverage-clover')]
    public ?string $clover = null;

    /** Target file for a Cobertura XML report (`--coverage-cobertura=<file>`). */
    #[InputOption('coverage-cobertura')]
    public ?string $cobertura = null;

    /** Target directory for a PHPUnit-style coverage XML report (`--coverage-xml=<dir>`). */
    #[InputOption('coverage-xml')]
    public ?string $xml = null;

    /** Requested depth of the analysis (`--coverage-level=line|branch|path`). */
    #[InputOption('coverage-level')]
    public ?string $level = null;

    public function resolveMode(): ?CoverageMode
    {
        return match (true) {
            $this->coverage => CoverageMode::Always,
            $this->noCoverage => CoverageMode::Never,
            default => null,
        };
    }

    /**
     * The level pinned on the CLI, or null when the flag is absent — the caller then keeps its own
     * configured level.
     *
     * @throws \InvalidArgumentException When the flag carries a value that names no level.
     */
    public function resolveLevel(): ?CoverageLevel
    {
        if ($this->level === null || $this->level === '') {
            return null;
        }

        return match (\strtolower($this->level)) {
            'line' => CoverageLevel::Line,
            'branch' => CoverageLevel::Branch,
            'path' => CoverageLevel::Path,
            default => throw new \InvalidArgumentException(\sprintf(
                'Unknown coverage level `%s` in `--coverage-level`. Expected one of: line, branch, path.',
                $this->level,
            )),
        };
    }

    /**
     * Builds the report writers requested via CLI flags. Empty paths are skipped.
     *
     * @return list<CoverageReport>
     */
    public function resolveReports(): array
    {
        $reports = [];
        if (($clover = $this->clover) !== null && $clover !== '') {
            $reports[] = new CloverReport($clover);
        }
        if (($cobertura = $this->cobertura) !== null && $cobertura !== '') {
            $reports[] = new CoberturaReport($cobertura);
        }
        if (($xml = $this->xml) !== null && $xml !== '') {
            $reports[] = new PhpUnitXmlReport($xml);
        }

        return $reports;
    }
}
