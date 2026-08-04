<?php

declare(strict_types=1);

namespace Testo\Fiber\Exception;

/**
 * Aggregates throwables raised by scheduled fibers — a {@see \Testo\Fiber\RunInFiber} case batch, or
 * coroutines of a test's scope ({@see \Testo\Fiber\Coroutine}).
 *
 * Coroutine failures are **always** surfaced through this wrapper, even a single one — whether
 * rethrown by `await()` / `concurrently()` or reported by the scope for a coroutine nobody awaited —
 * so handling code is uniform. The individual throwables stay reachable via {@see self::$errors},
 * and the earliest is chained as {@see \Throwable::getPrevious()} so ordinary renderers still show
 * a root cause.
 *
 * @api
 */
final class CompositeException extends \RuntimeException
{
    /**
     * The collected throwables, keyed by the fiber (task) index that raised each one.
     *
     * @var non-empty-array<int, \Throwable>
     */
    public readonly array $errors;

    /**
     * @param non-empty-array<int, \Throwable> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;

        $lines = \array_map(
            static fn(int $i, \Throwable $e): string => \sprintf('  #%d %s: %s', $i, $e::class, $e->getMessage()),
            \array_keys($errors),
            \array_values($errors),
        );

        parent::__construct(
            \sprintf(
                "%d fiber(s) failed:\n%s",
                \count($errors),
                \implode("\n", $lines),
            ),
            previous: $errors[\array_key_first($errors)],
        );
    }
}
