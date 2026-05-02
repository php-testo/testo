<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Codecov\Covers;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;

/**
 * Filters coverage data to only include lines matching {@see Covers} targets.
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

        if ($ranges === []) {
            return new CoverageResult();
        }

        $files = [];
        foreach ($coverage->files as $path => $fileCoverage) {
            $normalized = \str_replace('\\', '/', $path);

            if (!isset($ranges[$normalized])) {
                continue;
            }

            $filteredLines = [];
            foreach ($fileCoverage->lines as $line => $lineCoverage) {
                foreach ($ranges[$normalized] as [$start, $end]) {
                    if ($line >= $start && $line <= $end) {
                        $filteredLines[$line] = $lineCoverage;
                        break;
                    }
                }
            }

            $filteredLines !== [] and $files[$path] = new FileCoverage(
                $fileCoverage->path,
                $filteredLines,
                $fileCoverage->functions,
            );
        }

        return new CoverageResult($files);
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
