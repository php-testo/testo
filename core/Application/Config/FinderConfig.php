<?php

declare(strict_types=1);

namespace Testo\Application\Config;

use Internal\Path;

/**
 * File system scope configuration.
 */
final readonly class FinderConfig
{
    /**
     * @var Path[]
     * @readonly
     */
    public array $includes;

    /**
     * @var Path[]
     * @readonly
     */
    public array $excludes;

    /**
     * @param iterable<non-empty-string|Path> $include Include directories or files to the scope.
     *        A file listed here is kept even when an exclude covers it.
     * @param iterable<non-empty-string|Path> $exclude Exclude directories or files from the scope.
     *        Drops everything beneath each path, even a directory above an include.
     *
     * @note Glob and regex patterns are not supported
     */
    public function __construct(
        iterable $include = [],
        iterable $exclude = [],
    ) {
        $excludes = $includes = [];
        foreach ($include as $in) {
            $path = Path::create($in)->absolute();
            $path->exists() or throw new \InvalidArgumentException("File or directory not found: $path");
            $includes[] = $path;
        }

        foreach ($exclude as $ex) {
            $path = Path::create($ex)->absolute();
            $path->exists() or throw new \InvalidArgumentException("File or directory not found: $path");
            $excludes[] = $path;
        }

        $this->includes = $includes;
        $this->excludes = $excludes;
    }
}
