<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing;

/**
 * Declares the fixtures that exercise a Rector rule, so the rule can be tested "inline":
 * the rule itself points at its fixtures, which the bridge's test harness
 * ({@see Internal\Middleware\RectorFixtureFinder}) discovers and runs.
 *
 * Paths are resolved relative to the rule's own source file. A directory is scanned for
 * `*.php.inc` fixtures; a file is taken as-is. Each fixture holds the input and the expected
 * output separated by a `-----` line (no separator = the rule must leave the input unchanged).
 *
 * The attribute and the harness ship with the package (so downstream rule authors can reuse
 * them); the fixtures (`*.php.inc`) are `export-ignore`d (see `.gitattributes`).
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TestRectorFixtures
{
    /** @var list<non-empty-string> */
    public array $paths;

    /**
     * @param non-empty-string ...$paths Directories or files, relative to the rule's file.
     *        Defaults to a sibling `fixtures/` directory.
     */
    public function __construct(string ...$paths)
    {
        $this->paths = $paths === [] ? ['fixtures'] : \array_values($paths);
    }
}
