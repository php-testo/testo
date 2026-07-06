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
     * Absolute file or directory paths to filter by.
     *
     * Supports glob patterns: *, ?, [abc]
     *
     * @var list<Path>
     */
    public array $paths;

    /**
     * @param list<non-empty-string> $suites Test suite names to filter by
     * @param list<non-empty-string> $names Class, method, or function names to filter by
     * @param list<Path|string> $paths File or directory paths to filter by (supports glob patterns)
     * @param list<non-empty-string> $type Test case types to include (OR logic)
     * @param list<non-empty-string> $notType Test case types to exclude (takes precedence)
     * @param list<non-empty-string> $groups Group names to include (OR logic)
     * @param list<non-empty-string> $excludeGroups Group names to exclude (takes precedence)
     */
    public function __construct(
        /**
         * Test suite names to filter by.
         */
        public array $suites = [],
        /**
         * Class, method, or function names to filter by.
         *
         * Supports formats:
         * - Method: ClassName::methodName or Namespace\ClassName::methodName
         * - FQN: Namespace\ClassName or Namespace\functionName
         * - Fragment: methodName, functionName, or ShortClassName
         */
        public array $names = [],
        array $paths = [],
        /**
         * Test case types to include, e.g. 'test', 'inline', 'bench', etc. A case passes when its type
         * is in this list (OR logic). An empty list means no type inclusion filter is applied.
         * @see TestType
         */
        public array $type = [],
        /**
         * Test case types to exclude. A case is dropped when its type is in this list.
         * Exclusion takes precedence over inclusion.
         * @see TestType
         */
        public array $notType = [],
        /**
         * Group names to include. A test passes when its group set intersects this list (OR logic).
         * An empty list means no group inclusion filter is applied.
         *
         * @see \Testo\Filter\Group
         */
        public array $groups = [],
        /**
         * Group names to exclude. A test is dropped when its group set intersects this list.
         * Exclusion takes precedence over inclusion.
         *
         * @see \Testo\Filter\Group
         */
        public array $excludeGroups = [],
    ) {
        $this->paths = \array_map(static fn(string|Path $p): Path => Path::create($p)->absolute(), $paths);
    }

    /**
     * Create a new Filter instance with modified properties.
     *
     * @param list<non-empty-string>|null $testSuites New test suite names, or null to keep existing
     * @param list<non-empty-string>|null $names New names, or null to keep existing
     * @param list<Path|string>|null $paths New paths, or null to keep existing
     * @param list<non-empty-string>|null $type New include types, or null to keep existing
     * @param list<non-empty-string>|null $notType New exclude types, or null to keep existing
     * @param list<non-empty-string>|null $groups New include groups, or null to keep existing
     * @param list<non-empty-string>|null $excludeGroups New exclude groups, or null to keep existing
     *
     * @return self New Filter instance with updated properties
     */
    public function with(
        ?array $testSuites = null,
        ?array $names = null,
        ?array $paths = null,
        ?array $type = null,
        ?array $notType = null,
        ?array $groups = null,
        ?array $excludeGroups = null,
    ): self {
        return new self(
            suites: $testSuites ?? $this->suites,
            names: $names ?? $this->names,
            paths: $paths ?? $this->paths,
            type: $type ?? $this->type,
            notType: $notType ?? $this->notType,
            groups: $groups ?? $this->groups,
            excludeGroups: $excludeGroups ?? $this->excludeGroups,
        );
    }
}
