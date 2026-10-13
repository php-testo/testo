<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit;

use Testo\Application\Config\FinderConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Exception\SkipTest;
use Testo\Test;
use Testo\Tokenizer\Finder;

#[Test]
#[Covers(Finder::class)]
final class FinderTest
{
    /**
     * count() returns number of files found in the included directory.
     */
    public function countReturnsNumberOfFilesInIncludedDirectory(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));

        Assert::true($finder->count() > 0);
    }

    /**
     * getIterator() returns an iterable over the found files.
     */
    public function getIteratorReturnsIterable(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));

        $iterator = $finder->getIterator();
        Assert::true($iterator instanceof \IteratorAggregate);
    }

    /**
     * files() returns a new Finder that yields only files.
     */
    public function filesReturnsSelfWithFilesOnly(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));
        $filesFinder = $finder->files();

        Assert::true($filesFinder instanceof Finder);
        Assert::true($filesFinder !== $finder);
        Assert::true($filesFinder->count() > 0);
    }

    /**
     * directories() returns a new Finder that yields only directories.
     */
    public function directoriesReturnsSelfWithDirectoriesOnly(): void
    {
        $unitDir = \dirname(__DIR__);

        $finder = new Finder(new FinderConfig(include: [$unitDir]));
        $dirsFinder = $finder->directories();

        Assert::true($dirsFinder instanceof Finder);
        Assert::true($dirsFinder !== $finder);
    }

    /**
     * withFilter() returns a new Finder with the given closure applied.
     */
    public function withFilterReturnsSelfWithFilterApplied(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));
        $filtered = $finder->withFilter(static fn(\Symfony\Component\Finder\SplFileInfo $f): bool => false);

        Assert::true($filtered instanceof Finder);
        Assert::true($filtered !== $finder);
        Assert::same(0, $filtered->count());
    }

    /**
     * __clone deep-copies the underlying Symfony finder, so applying a filter to a
     * derived finder leaves the original untouched.
     */
    public function cloneIsIndependentFromOriginal(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $original = new Finder(new FinderConfig(include: [$stubDir]));
        $originalCount = $original->count();

        $derived = $original->withFilter(static fn(\Symfony\Component\Finder\SplFileInfo $f): bool => false);

        Assert::same(0, $derived->count());
        Assert::same($originalCount, $original->count());
    }

    /**
     * Constructor with include files (not dirs) appends them individually.
     */
    public function constructorWithIncludeFilesCountsThoseFiles(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $fileA = $stubDir . '/EmptyClass.php';
        $fileB = $stubDir . '/OnlyFunctions.php';

        $finder = new Finder(new FinderConfig(include: [$fileA, $fileB]));

        Assert::same(2, $finder->count());
    }

    /**
     * Constructor with an exclude directory filters out files from that dir.
     */
    public function constructorWithExcludeDirectoryFiltersFilesFromIt(): void
    {
        $testsTokenizerDir = \dirname(__DIR__, 2);
        $unitDir = \dirname(__DIR__) . '/Unit';

        $allFinder = new Finder(new FinderConfig(include: [$testsTokenizerDir]));
        $filteredFinder = new Finder(new FinderConfig(
            include: [$testsTokenizerDir],
            exclude: [$unitDir],
        ));

        Assert::true($filteredFinder->count() < $allFinder->count());
    }

    /**
     * An excluded file inside an included directory is absent from the results.
     */
    public function excludedFileIsNotFound(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $excludedFile = \realpath($stubDir . '/EmptyClass.php');

        $finder = (new Finder(new FinderConfig(
            include: [$stubDir],
            exclude: [$excludedFile],
        )))->files();

        $all = (new Finder(new FinderConfig(include: [$stubDir])))->files();
        Assert::false(\in_array($excludedFile, self::realPaths($finder), true));
        Assert::same($finder->count(), $all->count() - 1);
    }

    /**
     * An excluded file outside every included path does not join the results.
     */
    public function excludedFileOutsideIncludesIsNotAdded(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $outsideFile = \realpath(__FILE__);

        $finder = (new Finder(new FinderConfig(
            include: [$stubDir],
            exclude: [$outsideFile],
        )))->files();

        Assert::false(\in_array($outsideFile, self::realPaths($finder), true));
    }

    /**
     * A file listed in include by name is kept even under an excluded directory.
     */
    public function includedFileWinsOverExcludedDirectory(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $file = \realpath($stubDir . '/NoNamespace/GlobalClass.php');

        $finder = (new Finder(new FinderConfig(
            include: [$stubDir, $file],
            exclude: [$stubDir . '/NoNamespace'],
        )))->files();

        Assert::true(\in_array($file, self::realPaths($finder), true));
    }

    /**
     * Filters applied after construction see every entry, so an excluded file cannot slip past them.
     */
    public function withFilterRejectsEverythingWhenFileExcluded(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = (new Finder(new FinderConfig(
            include: [$stubDir],
            exclude: [$stubDir . '/EmptyClass.php'],
        )))->withFilter(static fn(\Symfony\Component\Finder\SplFileInfo $f): bool => false);

        Assert::same($finder->count(), 0);
    }

    /**
     * directories() yields no excluded file.
     */
    public function directoriesSkipExcludedFile(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = (new Finder(new FinderConfig(
            include: [$stubDir],
            exclude: [$stubDir . '/EmptyClass.php'],
        )))->directories();

        $paths = self::realPaths($finder);
        Assert::true(\in_array(\realpath($stubDir . '/NoNamespace'), $paths, true));
        Assert::false(\in_array(\realpath($stubDir . '/EmptyClass.php'), $paths, true));
    }

    /**
     * Constructor with an exclude directory: files inside it are absent from results,
     * files outside it are still present.
     */
    public function constructorWithExcludeDirectoryFiltersFilesInsideIt(): void
    {
        $tokenizerDir = \dirname(__DIR__, 2);
        $stubDir = \dirname(__DIR__) . '/Stub';

        $withExclusion = (new Finder(new FinderConfig(
            include: [$tokenizerDir],
            exclude: [$stubDir],
        )))->files();

        $foundPaths = [];
        foreach ($withExclusion as $file) {
            $foundPaths[] = $file->getRealPath();
        }

        // No file from $stubDir should appear
        $stubRealDir = \realpath($stubDir);
        foreach ($foundPaths as $p) {
            Assert::true(!\str_starts_with(\str_replace('\\', '/', $p), \str_replace('\\', '/', $stubRealDir)));
        }
        // But some files should still be found
        Assert::true(\count($foundPaths) > 0);
    }

    /**
     * Paths spelled in another letter case than the filesystem keeps them still take effect.
     */
    public function excludesMatchPathsSpelledInOtherLetterCase(): void
    {
        \DIRECTORY_SEPARATOR === '/' and throw new SkipTest('Paths are case-sensitive on this OS.');
        $stubDir = \realpath(\dirname(__DIR__) . '/Stub');
        $excludedFile = $stubDir . '\EmptyClass.php';
        $excludedDirFile = $stubDir . '\NoNamespace\GlobalClass.php';

        $finder = (new Finder(new FinderConfig(
            include: [\strtolower($stubDir)],
            exclude: [\strtoupper($excludedFile), \strtoupper(\dirname($excludedDirFile))],
        )))->files();

        $all = (new Finder(new FinderConfig(include: [$stubDir])))->files();
        $found = self::realPaths($finder);
        Assert::false(\in_array($excludedFile, $found, true));
        Assert::false(\in_array($excludedDirFile, $found, true));
        Assert::same($finder->count(), $all->count() - 2);
    }

    /**
     * Paths reached through a symlink take effect, in includes and excludes alike.
     */
    public function excludesMatchPathsReachedThroughSymlink(): void
    {
        $stubDir = \realpath(\dirname(__DIR__) . '/Stub');
        $link = \sys_get_temp_dir() . '/testo-finder-' . \bin2hex(\random_bytes(4));
        @\symlink($stubDir, $link) or throw new SkipTest('Symlinks cannot be created here.');

        try {
            $finder = (new Finder(new FinderConfig(
                include: [$link],
                exclude: [$link . '/EmptyClass.php', $link . '/NoNamespace'],
            )))->files();

            $all = (new Finder(new FinderConfig(include: [$stubDir])))->files();
            $found = self::realPaths($finder);
            Assert::false(\in_array(\realpath($stubDir . '/EmptyClass.php'), $found, true));
            Assert::false(\in_array(\realpath($stubDir . '/NoNamespace/GlobalClass.php'), $found, true));
            Assert::same($finder->count(), $all->count() - 2);
        } finally {
            \DIRECTORY_SEPARATOR === '/' ? \unlink($link) : \rmdir($link);
        }
    }

    /**
     * @return list<string> Sorted real paths of the entries.
     */
    private static function realPaths(Finder $finder): array
    {
        $paths = [];
        foreach ($finder as $file) {
            $paths[] = $file->getRealPath();
        }

        return $paths;
    }
}
