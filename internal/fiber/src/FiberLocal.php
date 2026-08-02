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

    /** @var T|null Value of an open driven scope — see {@see scope()}'s `$drive`. Visible to every fiber. */
    private mixed $ambient = null;

    private bool $hasAmbient = false;

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
        // scope, so there is nothing to inherit. A driven scope is the exception: it is ambient by design.
        return $fiber === null
            ? match (true) {
                $this->hasMain => $this->main,
                $this->hasAmbient => $this->ambient,
                default => $this->default,
            }
        : ($this->byFiber[$fiber] ?? $this->surrounding());
    }

    /**
     * Bind $value to the current context, run $run, then restore the previous binding — even if $run
     * throws. Re-entrant: nested scopes in the same fiber save and restore correctly.
     *
     * $destroy, if given, runs once after the binding is restored (a `finally` for the caller): it lets a
     * scope tear its value down without an extra try/finally around the call.
     *
     * Inside a {@see DrivenScopes} region the scope is **driven** instead of bound: $run gets a fiber of
     * its own that this call pumps, and the value is published to every fiber for as long as that body is
     * the code running — installed when the body is resumed, taken back down whenever it parks. Fibers the
     * body spawns then read the scope's value however deep they nest, which plain per-fiber binding cannot
     * offer (PHP gives a fiber no link to its creator). The price is that the body no longer runs on
     * `{main}`, so it cannot enter an event loop.
     *
     * That price is a contract, not a check: declare the region only where the fibers are driven by hand.
     * Nothing here can verify it, since a loop reports itself as running from its first use until the
     * process ends, which says nothing about who owns the fiber asking.
     *
     * @template R
     * @param T $value
     * @param \Closure(): R $run
     * @param \Closure(): void|null $destroy Cleanup for $value, run after the binding is restored.
     * @return R
     */
    public function scope(mixed $value, \Closure $run, ?\Closure $destroy = null): mixed
    {
        if (DrivenScopes::enabled()) {
            return $this->driveScope($value, $run, $destroy);
        }

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
     * Run $run in a fiber of our own and pump it, publishing $value to every fiber while that body holds
     * the floor: installed before each resume, restored to the enclosing value whenever the body parks.
     *
     * That swapping is what makes the value correct for whole trees of fibers the body spawns, and keeps
     * sibling driven scopes from seeing each other — the value in force always belongs to the subtree that
     * is running. Suspensions and injected throwables are relayed both ways, so from the outside a driven
     * scope suspends exactly like the body it wraps.
     *
     * @template R
     * @param T $value
     * @param \Closure(): R $run
     * @param \Closure(): void|null $destroy
     * @return R
     */
    private function driveScope(mixed $value, \Closure $run, ?\Closure $destroy): mixed
    {
        $hadOuter = $this->hasAmbient;
        $outer = $this->ambient;
        $body = new \Fiber($run);

        try {
            $this->ambient = $value;
            $this->hasAmbient = true;
            $parked = $body->start();

            while (!$body->isTerminated()) {
                \Fiber::getCurrent() === null and throw new \LogicException(
                    'The body of a driven scope suspended, but the scope was opened outside any fiber, so '
                    . 'there is nobody to suspend to. Open it inside a fiber, or drop $drive.',
                );

                // Parked: the floor belongs to whoever runs while we wait, so put their value back.
                $this->hasAmbient = $hadOuter;
                $this->ambient = $hadOuter ? $outer : null;

                $thrown = null;
                try {
                    $resumed = \Fiber::suspend($parked);
                } catch (\Throwable $e) {
                    // A throwable injected into us belongs to the body — relay it there.
                    $resumed = null;
                    $thrown = $e;
                }

                $this->ambient = $value;
                $this->hasAmbient = true;

                $parked = $thrown === null ? $body->resume($resumed) : $body->throw($thrown);
            }

            /** @var R */
            return $body->getReturn();
        } finally {
            $this->hasAmbient = $hadOuter;
            $this->ambient = $hadOuter ? $outer : null;
            $destroy === null or $destroy();
        }
    }

    /**
     * Value for a fiber that holds none of its own: an open driven scope's, else the single bound fiber's,
     * else the main thread's when no fiber is bound at all. A fiber binding wins over the main thread's —
     * with a scope open on the main thread and one running inside a fiber, an unbound fiber belongs to the
     * inner one.
     *
     * Two or more bound fibers mean concurrent scopes are open, and nothing here identifies which one the
     * caller descends from, so the default is returned rather than a guess.
     *
     * @return T
     */
    private function surrounding(): mixed
    {
        // A driven scope publishes its value while its body holds the floor, so it is never ambiguous.
        if ($this->hasAmbient) {
            return $this->ambient;
        }

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
