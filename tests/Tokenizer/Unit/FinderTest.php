<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit;

use Testo\Application\Config\FinderConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
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
     * Constructor with a single exclude file still constructs successfully and counts items.
     */
    public function constructorWithExcludeFileSucceeds(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $excludedFile = $stubDir . '/EmptyClass.php';

        $finder = new Finder(new FinderConfig(
            include: [$stubDir],
            exclude: [$excludedFile],
        ));

        // Construction succeeds and count is accessible (the finder is usable)
        Assert::true($finder->count() > 0);
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
}
