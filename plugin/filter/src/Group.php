<?php

declare(strict_types=1);

namespace Testo\Filter;

/**
 * Labels a class, method, or function with one or more group names for selective filtering.
 *
 * Groups are flat string labels — there is no key/value semantics. They let you run or skip
 * tests by category (e.g. `db`, `slow`, `integration`) without touching test names or paths.
 *
 * Selection happens through the `--group` CLI flag (or the {@see \Testo\Filter} DTO):
 * - `--group=db` runs only tests in group `db` (multiple `--group` values use OR logic).
 * - `--group=!slow` skips tests in group `slow` (the `!` prefix marks an exclusion).
 * - Group filters combine with name/path/suite filters using AND logic.
 *
 * Behavior depends on the target:
 *
 * On a class — every test of that case inherits the group. The group set of a test is the union
 * of all groups reachable from it: the method (and any overridden parent method it overrides),
 * the test class, its parent classes, and traits.
 *
 * ```
 *  #[Test]
 *  #[Group('integration')]
 *  final class OrderTest
 *  {
 *      public function createsOrder(): void { ... }            // groups: integration
 *
 *      #[Group('slow')]
 *      public function importsLargeDataset(): void { ... }     // groups: integration, slow
 *  }
 * ```
 *
 * On a method or function — only that test carries the group.
 *
 * ```
 *  final class OrderTest
 *  {
 *      #[Test]
 *      #[Group('db')]
 *      public function persistsOrder(): void { ... }
 *  }
 * ```
 *
 * The attribute is variadic — pass several group names at once:
 *
 * ```
 *  #[Group('db', 'slow')]
 * ```
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION)]
final readonly class Group
{
    /**
     * @var array<array-key, string>
     */
    public array $names;

    /**
     * @param string ...$names Group labels to assign.
     */
    public function __construct(string ...$names)
    {
        $this->names = $names;
    }
}
