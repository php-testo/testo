<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit;

use Testo\Application\Config\FinderConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Test;
use Testo\Tokenizer\FileLocator;
use Testo\Tokenizer\Finder;
use Testo\Tokenizer\Reflection\TokenizedFile;

#[Test]
#[Covers(FileLocator::class)]
final class FileLocatorTest
{
    /**
     * Constructor stores a files-only Finder, so getIterator() yields TokenizedFile objects.
     */
    public function constructorAcceptsFinderAndGetIteratorYieldsTokenizedFiles(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));
        $locator = new FileLocator($finder);

        $items = \iterator_to_array($locator->getIterator(), false);

        Assert::true(\count($items) > 0);
        Assert::instanceOf($items[0], TokenizedFile::class);
    }

    /**
     * fromFinderConfig with an empty Filter (no paths) creates a locator that discovers all files.
     */
    public function fromFinderConfigWithEmptyFilterDiscoversAllFiles(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $locator = FileLocator::fromFinderConfig(
            new FinderConfig(include: [$stubDir]),
            new Filter(),
        );

        $items = \iterator_to_array($locator->getIterator(), false);

        Assert::true(\count($items) > 0);
        Assert::instanceOf($items[0], TokenizedFile::class);
    }

    /**
     * fromFinderConfig with a Filter containing a matching path restricts results to that path.
     */
    public function fromFinderConfigWithPathFilterLimitsDiscoveredFiles(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        $targetFile = $stubDir . '/EmptyClass.php';

        $locator = FileLocator::fromFinderConfig(
            new FinderConfig(include: [$stubDir]),
            new Filter(paths: [$targetFile]),
        );

        $items = \iterator_to_array($locator->getIterator(), false);

        Assert::true(\count($items) > 0);
        $foundPaths = \array_map(static fn(TokenizedFile $f): string => $f->file->getRealPath(), $items);
        $realTarget = \realpath($targetFile);
        Assert::true(\in_array($realTarget, $foundPaths, true));
    }

    /**
     * fromFinderConfig with a non-matching path filter returns an empty result.
     */
    public function fromFinderConfigWithNonMatchingPathFilterReturnsEmpty(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';
        // Sibling directory of $stubDir: the finder scans $stubDir, so no discovered
        // file's real path starts with $sibling and the path filter rejects all of them.
        $sibling = \dirname(__DIR__) . '/Reflection';

        $locator = FileLocator::fromFinderConfig(
            new FinderConfig(include: [$stubDir]),
            new Filter(paths: [$sibling]),
        );

        $items = \iterator_to_array($locator->getIterator(), false);

        Assert::same([], $items);
    }

    /**
     * getIterator() yields one TokenizedFile per discovered PHP file.
     */
    public function getIteratorYieldsOneTokenizedFilePerDiscoveredFile(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $finder = new Finder(new FinderConfig(include: [$stubDir]));
        $locator = new FileLocator($finder);

        $count = 0;
        foreach ($locator->getIterator() as $file) {
            Assert::instanceOf($file, TokenizedFile::class);
            ++$count;
        }

        Assert::true($count > 0);
    }
}
