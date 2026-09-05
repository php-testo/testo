<?php

declare(strict_types=1);

namespace Tests\Tokenizer\Unit;

use Testo\Application\Config\FinderConfig;
use Testo\Assert;
use Testo\Codecov\Covers;
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
     * fromFinderConfig creates a locator that discovers all files of the configured scope.
     */
    public function fromFinderConfigDiscoversAllFiles(): void
    {
        $stubDir = \dirname(__DIR__) . '/Stub';

        $locator = FileLocator::fromFinderConfig(
            new FinderConfig(include: [$stubDir]),
        );

        $items = \iterator_to_array($locator->getIterator(), false);

        Assert::true(\count($items) > 0);
        Assert::instanceOf($items[0], TokenizedFile::class);
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
