<?php

declare(strict_types=1);

namespace Testo\Tokenizer;

use Internal\Path;
use Symfony\Component\Finder\Finder as SymfonyFinder;
use Symfony\Component\Finder\SplFileInfo;
use Testo\Application\Config\FinderConfig;

/**
 * @implements \IteratorAggregate<string, SplFileInfo>
 */
final class Finder implements \Countable, \IteratorAggregate
{
    private SymfonyFinder $finder;

    /**
     * @param FinderConfig $config Configuration for finder with absolute paths.
     */
    public function __construct(FinderConfig $config)
    {
        $this->finder = new SymfonyFinder();

        # Include
        $iDirs = $files = [];
        foreach ($config->includes as $path) {
            $path->isDir()
                ? $iDirs[] = (string) $path
                : $files[] = (string) $path;
        }
        $this->finder->in($iDirs);
        $this->finder->append($files);

        # Exclude: excluded paths only feed the filter, they are never searched or appended.
        # Found entries come as real paths, so excludes are resolved through symlinks to meet them.
        $excludes = [];
        foreach ($config->excludes as $path) {
            $excludes[] = Path::create(\realpath((string) $path) ?: $path);
        }

        $excludes === [] or $this->finder->filter(
            static function (\SplFileInfo $file) use ($excludes): bool {
                $path = Path::create($file->getRealPath());
                foreach ($excludes as $excluded) {
                    if ($path->isWithin($excluded)) {
                        return false;
                    }
                }

                return true;
            },
        );
    }

    /**
     * Get Finder for files only.
     *
     * @psalm-immutable
     */
    public function files(): self
    {
        $self = clone $this;
        $self->finder->files();
        return $self;
    }

    /**
     * Get Finder for directories only.
     *
     * @psalm-immutable
     */
    public function directories(): self
    {
        $self = clone $this;
        $self->finder->directories();
        return $self;
    }

    #[\Override]
    public function getIterator(): \IteratorAggregate
    {
        return $this->finder;
    }

    #[\Override]
    public function count(): int
    {
        return $this->finder->count();
    }

    /**
     * Apply a custom filter to the finder.
     *
     * @param \Closure(SplFileInfo): bool $filter A closure that defines the filtering logic.
     *
     * @psalm-immutable
     */
    public function withFilter(\Closure $filter): self
    {
        $self = clone $this;
        $self->finder->filter($filter);
        return $self;
    }

    public function __clone(): void
    {
        $this->finder = clone $this->finder;
    }
}
