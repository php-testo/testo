<?php

declare(strict_types=1);

namespace Testo\Common;

/**
 * Holds a value **per fiber**, keyed by {@see \Fiber::getCurrent()} through a {@see \WeakMap}, with a
 * separate slot for the main (no-fiber) thread.
 *
 * Testo's scoped-state guards (assertion collector, messenger hub, container, mockery) need each test to
 * see its own scoped state even while several tests interleave on one event loop. The historical approach
 * wrapped the scope in a child {@see \Fiber} and swapped a process-global slot back and forth at every
 * suspension boundary — which assumes the guard is the sole driver of the fiber and deadlocks once a real
 * event loop (Revolt) resumes the exact suspended fiber directly, bypassing the guard's trampoline.
 *
 * Fiber-local storage needs no trampoline: each fiber reads its own slot, so the loop may drive fibers
 * freely. When the loop switches from fiber A to fiber B, B reads its own entry and A's stays untouched;
 * A resumes with its slot intact. The {@see \WeakMap} drops a fiber's entry once the fiber is collected,
 * so no state leaks across tests.
 *
 * @template T
 * @internal
 * @psalm-internal Testo
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
     * @return T The value bound to the current fiber (or the main thread), or the default.
     */
    public function get(): mixed
    {
        $fiber = \Fiber::getCurrent();

        return $fiber === null
            ? ($this->hasMain ? $this->main : $this->default)
            : ($this->byFiber[$fiber] ?? $this->default);
    }

    /**
     * @param T $value Bind a value to the current fiber (or the main thread).
     */
    public function set(mixed $value): void
    {
        $fiber = \Fiber::getCurrent();
        if ($fiber === null) {
            $this->main = $value;
            $this->hasMain = true;
            return;
        }

        $this->byFiber[$fiber] = $value;
    }

    /**
     * Bind $value to the current context, run $run, then restore the previous binding.
     *
     * This one call replaces the whole `if (\Fiber::getCurrent() === null) { … } else { new \Fiber + while
     * (!isTerminated()) { swap; suspend; swap; resume } }` trampoline. Re-entrant: nested scopes in the same
     * fiber save and restore correctly.
     *
     * @template R
     * @param T $value
     * @param \Closure(): R $run
     * @return R
     */
    public function scope(mixed $value, \Closure $run): mixed
    {
        $fiber = \Fiber::getCurrent();

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
    }
}
