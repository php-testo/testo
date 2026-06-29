<?php

declare(strict_types=1);

namespace Testo\Pipeline;

/**
 * Options applied when building an interceptor pipeline.
 *
 * Currently carries the test-type selection used by {@see \Testo\Pipeline\Internal\Sorter} to decide
 * which interceptors take part in a pipeline, based on their {@see Attribute\InterceptorOptions::$testType}.
 * More options may be added here over time.
 *
 * Type selection rule: a type `t` survives when (`includeTypes` is empty or `t` is in `includeTypes`)
 * and `t` is not in `excludeTypes`; exclusion takes precedence over inclusion. An interceptor is kept
 * when it is universal (declares no type) or at least one of its declared types survives.
 *
 * @psalm-immutable
 * @api
 */
final readonly class PipeOptions
{
    /**
     * @param list<non-empty-string> $includeTypes Types to include (OR logic). Empty means every type.
     * @param list<non-empty-string> $excludeTypes Types to exclude (takes precedence over include).
     */
    public function __construct(
        public array $includeTypes = [],
        public array $excludeTypes = [],
    ) {}

    /**
     * Whether any test-type filtering is requested (every interceptor is kept when it is not).
     */
    public function hasTypeFilter(): bool
    {
        return $this->includeTypes !== [] || $this->excludeTypes !== [];
    }

    /**
     * Whether an interceptor declaring the given types should take part in the pipeline.
     *
     * @param list<non-empty-string> $declaredTypes Types the interceptor applies to; empty means it
     *        is universal and applies to all types.
     */
    public function acceptsTypes(array $declaredTypes): bool
    {
        # Universal interceptors apply to every surviving type.
        if ($declaredTypes === []) {
            return true;
        }

        foreach ($declaredTypes as $type) {
            # Exclude takes precedence.
            if ($this->excludeTypes !== [] && \in_array($type, $this->excludeTypes, true)) {
                continue;
            }

            if ($this->includeTypes === [] || \in_array($type, $this->includeTypes, true)) {
                return true;
            }
        }

        return false;
    }
}
