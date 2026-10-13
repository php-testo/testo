<?php

declare(strict_types=1);

namespace Testo\Codecov\Internal;

use Testo\Application\Config\FinderConfig;
use Testo\Codecov\Result\CoverageResult;
use Testo\Codecov\Result\FileCoverage;
use Testo\Codecov\Result\LineCoverage;
use Testo\Codecov\Result\LineStatus;
use Testo\Tokenizer\Finder;

/**
 * Adds the source files no test loaded to a coverage result, with every executable line unexecuted.
 *
 * A driver reports only the files PHP compiled during the run, so without this a class that has no
 * tests is missing from the report instead of lowering the total. The lines of such a file come from
 * {@see ExecutableLines}, since it cannot be loaded without running its top-level code.
 *
 * @internal
 */
final class UnloadedFiles
{
    public static function add(CoverageResult $result, FinderConfig $src): CoverageResult
    {
        if ($src->includes === []) {
            return $result;
        }

        # Drivers key files by the path PHP resolved, with the platform's separators.
        $known = [];
        foreach ($result->files as $path => $_) {
            $known[self::key($path)] = true;
        }

        # A file reached through a link that leads out of the scope is one the drivers leave out.
        $roots = [];
        foreach ($src->includes as $include) {
            $real = \realpath((string) $include);
            $real === false or $roots[] = self::key($real);
        }

        $files = [];
        foreach ((new Finder($src))->files()->withFilter(self::isPhp(...)) as $file) {
            $path = $file->getRealPath();
            if ($path === false || isset($known[self::key($path)]) || !self::inside(self::key($path), $roots)) {
                continue;
            }

            $lines = [];
            foreach (ExecutableLines::of((string) \file_get_contents($path)) as $line => $_) {
                $lines[$line] = new LineCoverage($line, LineStatus::NotExecuted);
            }

            $lines === [] or $files[$path] = new FileCoverage($path, $lines);
        }

        return $files === [] ? $result : $result->merge(new CoverageResult($files));
    }

    private static function isPhp(\SplFileInfo $file): bool
    {
        return $file->getExtension() === 'php';
    }

    /**
     * @param list<string> $roots
     */
    private static function inside(string $path, array $roots): bool
    {
        foreach ($roots as $root) {
            if ($path === $root || \str_starts_with($path, \rtrim($root, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function key(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
