<?php

declare(strict_types=1);

namespace Testo\Codecov\Result;

use Testo\Codecov\Config\CoverageLevel;

/**
 * Code coverage data for a single source file.
 *
 * Line coverage is always present. Branch and path coverage (via {@see $functions})
 * is optional and only populated when collected with {@see CoverageLevel::Branch}
 * or {@see CoverageLevel::Path}.
 */
final readonly class FileCoverage
{
    public function __construct(
        /** @var non-empty-string Absolute path to the source file. */
        public string $path,

        /** @var array<int<0, max>, LineStatus> Line number => coverage status. */
        public array $lines,

        /** @var array<non-empty-string, FunctionCoverage> Function name => branch/path data. */
        public array $functions = [],
    ) {}

    /**
     * Merges another coverage into this one.
     * A line is considered Executed if it was executed in either coverage run.
     */
    public function merge(self $other): self
    {
        $mergedLines = $this->lines;
        foreach ($other->lines as $line => $status) {
            $mergedLines[$line] = match (true) {
                !isset($mergedLines[$line]) => $status,
                $mergedLines[$line] === LineStatus::Executed,
                $status === LineStatus::Executed => LineStatus::Executed,
                default => $mergedLines[$line],
            };
        }

        $mergedFunctions = $this->functions;
        foreach ($other->functions as $name => $function) {
            $mergedFunctions[$name] = isset($mergedFunctions[$name])
                ? $mergedFunctions[$name]->merge($function)
                : $function;
        }

        return new self($this->path, $mergedLines, $mergedFunctions);
    }
}
