<?php

declare(strict_types=1);

namespace Testo;

use Internal\Path;
use Testo\Core\Value\TestType;

/**
 * Immutable DTO containing test filtering criteria.
 *
 * Can be created manually and passed to Application::run() or populated automatically
 * from CLI arguments.
 *
 * @api
 */
final readonly class Filter
{
    /**
     * Test suite names to filter by.
     *
     * @var list<non-empty-string>
     */
    public array $suites;

    /**
     * Class, method, or function names to filter by.
     *
     * Supports formats:
     * - Method: ClassName::methodName or Namespace\ClassName::methodName
     * - FQN: Namespace\ClassName or Namespace\functionName
     * - Fragment: methodName, functionName, or ShortClassName
     *
     * @var list<non-empty-string>
     */
    public array $names;

    /**
     * Absolute file or directory paths to filter by.
     *
     * Supports glob patterns: *, ?, [abc]
     *
     * @var list<Path>
     */
    public array $paths;

    /**
     * Optional type filter for test cases, e.g. 'test', 'unit', 'inline', 'bench', etc.
     * @see TestType
     *
     * @var non-empty-string|null
     */
    public ?string $type;

    /**
     * Group names to include. A test passes when its group set intersects this list (OR logic).
     * An empty list means no group inclusion filter is applied.
     *
     * @see \Testo\Filter\Group
     *
     * @var list<non-empty-string>
     */
    public array $groups;

    /**
     * Group names to exclude. A test is dropped when its group set intersects this list.
     * Exclusion takes precedence over inclusion.
     *
     * @see \Testo\Filter\Group
     *
     * @var list<non-empty-string>
     */
    public array $excludeGroups;

    /**
     * @param list<non-empty-string> $suites Test suite names to filter by
     * @param list<non-empty-string> $names Class, method, or function names to filter by
     * @param Path $paths File or directory paths to filter by (supports glob patterns)
     * @param non-empty-string|null $type Optional type filter for test cases
     * @param list<non-empty-string> $groups Group names to include (OR logic)
     * @param list<non-empty-string> $excludeGroups Group names to exclude (takes precedence)
     */
    public function __construct(
        array $suites = [],
        array $names = [],
        array $paths = [],
        ?string $type = null,
        array $groups = [],
        array $excludeGroups = [],
    ) {
        $this->suites = $suites;
        $this->names = $names;
        $this->paths = \array_map(static fn(string|Path $p): Path => Path::create($p)->absolute(), $paths);
        $this->type = $type;
        $this->groups = $groups;
        $this->excludeGroups = $excludeGroups;
    }

    /**
     * Create a new Filter instance with modified properties.
     *
     * @param list<non-empty-string>|null $testSuites New test suite names, or null to keep existing
     * @param list<non-empty-string>|null $names New names, or null to keep existing
     * @param Path|null $paths New paths, or null to keep existing
     * @param string|null $type New type, empty string to set null, or null to keep existing
     * @param list<non-empty-string>|null $groups New include groups, or null to keep existing
     * @param list<non-empty-string>|null $excludeGroups New exclude groups, or null to keep existing
     *
     * @return self New Filter instance with updated properties
     */
    public function with(
        ?array $testSuites = null,
        ?array $names = null,
        ?array $paths = null,
        ?string $type = null,
        ?array $groups = null,
        ?array $excludeGroups = null,
    ): self {
        return new self(
            suites: $testSuites ?? $this->suites,
            names: $names ?? $this->names,
            paths: $paths ?? $this->paths,
            type: match ($type) {
                null => $this->type,
                '' => null,
                default => $type,
            },
            groups: $groups ?? $this->groups,
            excludeGroups: $excludeGroups ?? $this->excludeGroups,
        );
    }
}
