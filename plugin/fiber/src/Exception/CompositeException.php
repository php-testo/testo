<?php

declare(strict_types=1);

namespace Testo\Fiber\Exception;

/**
 * Aggregates every throwable raised by the fibers of a single {@see \Testo\Fiber\RunInFiber} case batch.
 *
 * Testo's per-test pipeline never throws (a failure becomes a result), so the fiber scheduler surfacing
 * even one throwable means something broke below the pipeline. When more than one fiber fails in an
 * interleaved run this bundles them all, instead of dropping every failure but the first — the
 * individual throwables stay reachable via {@see self::$errors}, and the earliest is chained as
 * {@see \Throwable::getPrevious()} so ordinary renderers still show a root cause.
 *
 * @api
 */
final class CompositeException extends \RuntimeException
{
    /**
     * The collected throwables, keyed by the fiber (test) index that raised each one.
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
                "%d test fiber(s) failed while running the case batch:\n%s",
                \count($errors),
                \implode("\n", $lines),
            ),
            previous: $errors[\array_key_first($errors)],
        );
    }
}
