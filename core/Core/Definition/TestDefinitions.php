<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

/**
 * Collection of the runnable definitions located in a Test Case: one flat set of
 * {@see TestDefinition}s, tests and non-tests (lifecycle hooks, helpers) alike. Interceptors refine
 * a case through the definitions' mutable flags rather than by adding or removing entries; see
 * {@see self::all()}, {@see self::filter()} and {@see self::getTests()}.
 *
 * @api
 */
final class TestDefinitions
{
    /**
     * Every runnable definition of the case, keyed by short name.
     *
     * @var array<non-empty-string, TestDefinition>
     */
    private array $definitions = [];

    /**
     * Create TestDefinitions from an array of TestDefinition.
     *
     * @note You must use named non-empty-string arguments to preserve keys.
     */
    public static function fromArray(TestDefinition ...$values): self
    {
        $self = new self();
        $self->definitions = $values;
        return $self;
    }

    /**
     * Register a function of the case and return its definition. Idempotent per short name, so
     * several finders may register the same function in any order: a test registration promotes an
     * existing non-test, a non-test registration never demotes an existing test.
     */
    public function define(\ReflectionFunctionAbstract $reflection, bool $isTest = true): TestDefinition
    {
        $definition = $this->definitions[$reflection->getShortName()] ??= new TestDefinition($reflection, isTest: $isTest);
        $isTest and $definition->isTest = true;

        return $definition;
    }

    /**
     * Every definition of the case, whatever its flags.
     *
     * @return array<non-empty-string, TestDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * The definitions matching the given flag constraints, keyed by short name. A `null` constraint
     * matches either value, so `filter(isTest: false)` returns the non-tests (lifecycle hooks,
     * helpers) regardless of their active state.
     *
     * @return array<non-empty-string, TestDefinition>
     */
    public function filter(?bool $isTest = null, ?bool $active = null): array
    {
        return \array_filter(
            $this->definitions,
            static fn(TestDefinition $d): bool => ($isTest === null || $d->isTest === $isTest)
                && ($active === null || $d->active === $active),
        );
    }

    /**
     * The case's tests — by default only the active ones, i.e. the definitions to run. Pass
     * `$active = false` for the deactivated tests alone, or `null` for every test regardless of
     * state.
     *
     * @return array<non-empty-string, TestDefinition>
     */
    public function getTests(?bool $active = true): array
    {
        return $this->filter(isTest: true, active: $active);
    }

    /**
     * Reorder the definitions in place using a user comparator. Keys (short names) are preserved, so
     * the reordering changes only execution/iteration order, not identity.
     *
     * @param callable(TestDefinition, TestDefinition): int $comparator
     */
    public function sort(callable $comparator): void
    {
        \uasort($this->definitions, $comparator);
    }
}
