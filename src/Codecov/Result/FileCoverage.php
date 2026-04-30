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

        /** @var array<int<0, max>, LineCoverage> Line number => coverage data. */
        public array $lines,

        /** @var array<non-empty-string, FunctionCoverage> Function name => branch/path data. */
        public array $functions = [],
    ) {}

    /**
     * Merges another coverage into this one.
     *
     * Line status follows the OR rule (any executed run wins); test method lists are unioned.
     */
    public function merge(self $other): self
    {
        $mergedLines = $this->lines;
        foreach ($other->lines as $line => $lineCoverage) {
            $mergedLines[$line] = isset($mergedLines[$line])
                ? $mergedLines[$line]->merge($lineCoverage)
                : $lineCoverage;
        }

        $mergedFunctions = $this->functions;
        foreach ($other->functions as $name => $function) {
            $mergedFunctions[$name] = isset($mergedFunctions[$name])
                ? $mergedFunctions[$name]->merge($function)
                : $function;
        }

        return new self($this->path, $mergedLines, $mergedFunctions);
    }

    /**
     * Returns a copy with the given test method stamped on every executed line.
     *
     * Used by {@see \Testo\Codecov\Internal\Middleware\CoverageTestInterceptor} to
     * record per-test attribution before merging into the suite-wide aggregate.
     *
     * @param non-empty-string $method
     */
    public function withTestMethod(string $method): self
    {
        $stamped = [];
        foreach ($this->lines as $line => $lineCoverage) {
            $stamped[$line] = $lineCoverage->withTestMethod($method);
        }

        return new self($this->path, $stamped, $this->functions);
    }
}
