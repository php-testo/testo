<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

use Testo\Tokenizer\Reflection\FileDefinitions;

/**
 * Collection of test cases located in a file.
 *
 * @api
 */
final class CaseDefinitions
{
    /**
     * Located test cases.
     * @var array<non-empty-string, list<CaseDefinition>>
     */
    private array $cases = [];

    public static function fromArray(CaseDefinition ...$values): self
    {
        $self = new self();
        foreach ($values as $case) {
            $self->cases[$case->type][] = $case;
        }

        return $self;
    }

    /**
     * Get or create the case of the given type for a class (or, with a null reflection, for the
     * file's free functions).
     *
     * With `$prefill` a newly created case is seeded with every candidate it has — all methods of the
     * class, inherited ones included, or all free functions of the file — registered as non-tests.
     * Finders then promote the ones they recognise via {@see TestDefinitions::define()}, while the
     * rest stay reachable as non-tests (lifecycle hooks, helpers) without re-reading the file.
     * Finders whose tests are the only members of interest (e.g. inline tests) pass `false`.
     */
    public function define(
        ?\ReflectionClass $reflection,
        FileDefinitions $file,
        string|\BackedEnum $type = 'test',
        ?\Closure $handler = null,
        bool $prefill = true,
    ): CaseDefinition {
        \is_string($type) or $type = (string) $type->value;
        \assert($type !== '');

        $this->cases[$type] ??= [];
        foreach ($this->cases[$type] as $case) {
            if ($case->reflection === $reflection) {
                return $case;
            }
        }

        $case = new CaseDefinition(
            name: $reflection?->getShortName() ?? $file->tokenizedFile->path->name(),
            type: $type,
            file: $file->tokenizedFile->path,
            reflection: $reflection,
            handler: $handler,
        );

        if ($prefill) {
            foreach ($reflection?->getMethods() ?? $file->functions as $function) {
                $case->tests->define($function, isTest: false);
            }
        }

        return $this->cases[$type][] = $case;
    }

    /**
     * Get all located test cases.
     *
     * @return list<CaseDefinition>
     */
    public function getCases(): array
    {
        return \array_merge(...\array_values($this->cases));
    }

    /**
     * Reorder the cases in place using a user comparator. Cases are grouped by type, so the
     * comparator reorders the cases within each type group (the common single-type suite is sorted
     * as a whole).
     *
     * @param callable(CaseDefinition, CaseDefinition): int $comparator
     */
    public function sort(callable $comparator): void
    {
        foreach ($this->cases as &$cases) {
            \usort($cases, $comparator);
        }
    }
}
