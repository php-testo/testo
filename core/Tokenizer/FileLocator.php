<?php

declare(strict_types=1);

namespace Testo\Tokenizer;

use Testo\Application\Config\FinderConfig;
use Testo\Tokenizer\Reflection\TokenizedFile;

/**
 * Locates and tokenizes PHP files within a given FS scope.
 *
 * Reads files discovered by {@see Finder}, tokenizes their contents,
 * and creates {@see TokenizedFile} objects.
 *
 * @implements \IteratorAggregate<int, TokenizedFile>
 */
final readonly class FileLocator implements \IteratorAggregate
{
    protected Finder $finder;

    public function __construct(
        Finder $finder,
        protected bool $debug = false,
    ) {
        $this->finder = $finder->files();
    }

    public static function fromFinderConfig(FinderConfig $config): self
    {
        return new self(new Finder($config));
    }

    /**
     * Available file reflections. Generator.
     *
     * @return \Generator<int, TokenizedFile, mixed, void>
     * @throws \Exception
     */
    #[\Override]
    public function getIterator(): \Generator
    {
        foreach ($this->finder->getIterator() as $file) {
            yield new TokenizedFile($file, (string) $file);
        }
    }
}
