<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Codecov\Covers;
use Testo\Codecov\Result\BranchCoverage;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\FunctionCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Codecov\Result\PathCoverage;

/**
 * Narrows the execution a test is credited with to its {@see Covers} targets.
 *
 * Lines and branches outside the targets stay in the result as not executed instead of being dropped:
 * every executable line of a loaded file keeps its place in the report, so code no test claims still
 * counts against the total.
 *
 * @internal
 */
final class CoverageFilter
{
    /**
     * @param list<Covers> $targets
     */
    public static function apply(CoverageResult $coverage, array $targets): CoverageResult
    {
        $ranges = self::resolveRanges($targets);

        $files = [];
        foreach ($coverage->files as $path => $fileCoverage) {
            $fileRanges = $ranges[\str_replace('\\', '/', $path)] ?? [];

            $lines = [];
            foreach ($fileCoverage->lines as $line => $lineCoverage) {
                $lines[$line] = $lineCoverage->status === LineStatus::Executed && !self::inRanges($line, $fileRanges)
                    ? new LineCoverage($line, LineStatus::NotExecuted)
                    : $lineCoverage;
            }

            $functions = [];
            foreach ($fileCoverage->functions as $name => $function) {
                $functions[$name] = self::functionInRanges($function, $fileRanges)
                    ? $function
                    : self::withoutHits($function);
            }

            $files[$path] = new FileCoverage($fileCoverage->path, $lines, $functions);
        }

        return new CoverageResult($files, $coverage->sourceRoot);
    }

    /**
     * @param list<array{int, int}> $ranges
     */
    private static function inRanges(int $line, array $ranges): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($line >= $start && $line <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Judged by the first branch alone: a function lies wholly inside or wholly outside a target's range.
     *
     * @param list<array{int, int}> $ranges
     */
    private static function functionInRanges(FunctionCoverage $function, array $ranges): bool
    {
        foreach ($function->branches as $branch) {
            return self::inRanges($branch->lineStart, $ranges);
        }

        return false;
    }

    private static function withoutHits(FunctionCoverage $function): FunctionCoverage
    {
        return new FunctionCoverage(
            $function->name,
            \array_map(
                static fn(BranchCoverage $b): BranchCoverage => new BranchCoverage(
                    $b->opStart,
                    $b->opEnd,
                    $b->lineStart,
                    $b->lineEnd,
                    false,
                    $b->out,
                    \array_fill(0, \count($b->outHit), false),
                ),
                $function->branches,
            ),
            \array_map(static fn(PathCoverage $p): PathCoverage => new PathCoverage($p->path, false), $function->paths),
        );
    }

    /**
     * Resolves Covers targets to a map of file → line ranges.
     *
     * @param list<Covers> $targets
     * @return array<string, list<array{int, int}>> Normalized file path → [[startLine, endLine], ...]
     */
    private static function resolveRanges(array $targets): array
    {
        $ranges = [];

        foreach ($targets as $covers) {
            if ($covers->method !== null) {
                // Class::method
                self::addMethodRange($ranges, $covers->classOrFunction, $covers->method);
            } elseif (\class_exists($covers->classOrFunction) || \trait_exists($covers->classOrFunction)) {
                self::addClassRange($ranges, $covers->classOrFunction);
            } elseif (\function_exists($covers->classOrFunction)) {
                self::addFunctionRange($ranges, $covers->classOrFunction);
            }
        }

        return $ranges;
    }

    /**
     * @param array<string, list<array{int, int}>> $ranges
     */
    private static function addClassRange(array &$ranges, string $class): void
    {
        try {
            $ref = new \ReflectionClass($class);
        } catch (\ReflectionException) {
            return;
        }

        $file = $ref->getFileName();

        if ($file === false) {
            return;
        }

        $file = \str_replace('\\', '/', $file);
        $ranges[$file][] = [$ref->getStartLine(), $ref->getEndLine()];
    }

    /**
     * @param array<string, list<array{int, int}>> $ranges
     */
    private static function addMethodRange(array &$ranges, string $class, string $method): void
    {
        try {
            $ref = new \ReflectionMethod($class, $method);
        } catch (\ReflectionException) {
            return;
        }

        $file = $ref->getFileName();

        if ($file === false) {
            return;
        }

        $file = \str_replace('\\', '/', $file);
        $ranges[$file][] = [$ref->getStartLine(), $ref->getEndLine()];
    }

    /**
     * @param array<string, list<array{int, int}>> $ranges
     */
    private static function addFunctionRange(array &$ranges, string $function): void
    {
        try {
            $ref = new \ReflectionFunction($function);
        } catch (\ReflectionException) {
            return;
        }

        $file = $ref->getFileName();

        if ($file === false) {
            return;
        }

        $file = \str_replace('\\', '/', $file);
        $ranges[$file][] = [$ref->getStartLine(), $ref->getEndLine()];
    }
}
