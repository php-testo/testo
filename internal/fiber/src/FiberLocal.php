<?php

declare(strict_types=1);

namespace Internal\Fiber;

/**
 * A container whose value is scoped to the current fiber.
 *
 * Each fiber sees its own value; the main (no-fiber) thread has its own slot too. While several fibers hold
 * values at once none of them observes another's, so interleaving fibers stay isolated with no manual
 * save/restore at their switch points. A fiber's value is dropped once the fiber is garbage-collected.
 *
 * A fiber that holds no value of its own falls back to the surrounding one **when that is unambiguous**:
 * PHP gives a fiber no link to whoever created it, so this stands in for inheritance. With exactly one
 * fiber holding a value, everything running is under it and an unbound fiber reads that value; with none,
 * the main thread's value is used. Once two or more fibers hold values — genuine concurrency — the default
 * is returned instead, since answering with one of several concurrent scopes would be a guess.
 *
 * ```php
 * $local = new FiberLocal('default');
 *
 * $local->get();               // 'default' — nothing bound in this context yet
 *
 * // scope() binds a value for the duration of a callback, then restores the previous one:
 * $local->scope('scoped', function () use ($local): void {
 *     $local->get();           // 'scoped'
 * });
 * $local->get();               // 'default' — restored
 * ```
 *
 * @template T
 */
final class FiberLocal
{
    /** @var \WeakMap<\Fiber, T> */
    private \WeakMap $byFiber;

    /** @var T|null */
    private mixed $main = null;

    private bool $hasMain = false;

    /**
     * @param T $default Value seen before anything is set in the current context.
     */
    public function __construct(
        private readonly mixed $default = null,
    ) {
        /** @psalm-suppress PropertyTypeCoercion an empty WeakMap is WeakMap<object, mixed> until keyed */
        $this->byFiber = new \WeakMap();
    }

    /**
     * @return T The value bound to the current fiber (or the main thread); for a fiber with no binding of
     *         its own, the surrounding value when it is unambiguous, else the default.
     */
    public function get(): mixed
    {
        $fiber = \Fiber::getCurrent();

        // On the main thread an empty slot is not a missing parent — it is code running outside every
        // scope, so there is nothing to inherit.
        return $fiber === null
            ? ($this->hasMain ? $this->main : $this->default)
            : ($this->byFiber[$fiber] ?? $this->surrounding());
    }

    /**
     * Bind $value to the current context, run $run, then restore the previous binding — even if $run
     * throws. Re-entrant: nested scopes in the same fiber save and restore correctly.
     *
     * $destroy, if given, runs once after the binding is restored (a `finally` for the caller): it lets a
     * scope tear its value down without an extra try/finally around the call.
     *
     * @template R
     * @param T $value
     * @param \Closure(): R $run
     * @param \Closure(): void|null $destroy Cleanup for $value, run after the binding is restored.
     * @return R
     */
    public function scope(mixed $value, \Closure $run, ?\Closure $destroy = null): mixed
    {
        $fiber = \Fiber::getCurrent();

        try {
            if ($fiber === null) {
                $hadOld = $this->hasMain;
                $old = $this->main;
                $this->main = $value;
                $this->hasMain = true;
                try {
                    return $run();
                } finally {
                    $this->hasMain = $hadOld;
                    $this->main = $hadOld ? $old : null;
                }
            }

            // Split on had/not-had so the restored value keeps its non-null type T.
            if (isset($this->byFiber[$fiber])) {
                $old = $this->byFiber[$fiber];
                $this->byFiber[$fiber] = $value;
                try {
                    return $run();
                } finally {
                    /** @psalm-suppress PossiblyNullArgument $old came from an isset() slot, so it is a real T */
                    $this->byFiber[$fiber] = $old;
                }
            }

            $this->byFiber[$fiber] = $value;
            try {
                return $run();
            } finally {
                unset($this->byFiber[$fiber]);
            }
        } finally {
            $destroy === null or $destroy();
        }
    }

    /**
     * Value for a fiber that holds none of its own: the single bound fiber's, or the main thread's when no
     * fiber is bound at all. A fiber binding wins over the main thread's — with a scope open on the main
     * thread and one running inside a fiber, an unbound fiber belongs to the inner one.
     *
     * Two or more bound fibers mean concurrent scopes are open, and nothing here identifies which one the
     * caller descends from, so the default is returned rather than a guess.
     *
     * @return T
     */
    private function surrounding(): mixed
    {
        $bound = \count($this->byFiber);

        if ($bound === 1) {
            /** @var T $value */
            foreach ($this->byFiber as $value) {
                return $value;
            }
        }

        return $bound === 0 && $this->hasMain ? $this->main : $this->default;
    }
}
