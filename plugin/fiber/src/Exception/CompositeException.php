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
     * @param non-empty-array<array-key, \Throwable> $errors
     */
    public function __construct(/**
     * The collected throwables, keyed by whatever names each fiber to the producer: the task id for
     * scope/batch failures, or the argument key for {@see \Testo\Fiber\Coroutine::concurrently()}.
     */
    public readonly array $errors)
    {
        $lines = \array_map(
            static fn(int|string $key, \Throwable $e): string => \sprintf(
                '  %s %s: %s',
                \is_int($key) ? "#$key" : $key,
                $e::class,
                $e->getMessage(),
            ),
            \array_keys($this->errors),
            \array_values($this->errors),
        );

        parent::__construct(
            \sprintf(
                "%d fiber(s) failed:\n%s",
                \count($this->errors),
                \implode("\n", $lines),
            ),
            previous: $this->errors[\array_key_first($this->errors)],
        );
    }
}
