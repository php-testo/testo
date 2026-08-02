<?php

declare(strict_types=1);

namespace Internal\Fiber;

/**
 * Marks a region in which {@see FiberLocal} scopes are **driven** rather than bound per fiber.
 *
 * Whether a scope can be driven depends on who switches fibers around it, not on the scope itself — so it
 * is declared once, here, instead of threaded as an argument through every API that happens to open one.
 *
 * ```php
 * DrivenScopes::run(static function () use ($container): void {
 *     $container->scope(static function (Container $scoped): void {
 *         // fibers spawned in here — at any depth — resolve through $scoped
 *     });
 * });
 * ```
 *
 * Inside the region a scope runs its body on a fiber the scope itself pumps, publishing its value to every
 * fiber while that body holds the floor; see {@see FiberLocal::scope()} for the mechanics and the price.
 * Only declare it where the fibers are driven by hand: a driven body does not run on `{main}` and therefore
 * cannot enter an event loop.
 */
final class DrivenScopes
{
    /** Nesting depth of the declared regions; scopes are driven while it is above zero. */
    private static int $depth = 0;

    private function __construct() {}

    /**
     * Run $run with scopes driven, restoring the previous setting afterwards.
     *
     * @template T
     * @param \Closure(): T $run
     * @return T
     */
    public static function run(\Closure $run): mixed
    {
        ++self::$depth;

        try {
            return $run();
        } finally {
            --self::$depth;
        }
    }

    public static function enabled(): bool
    {
        return self::$depth > 0;
    }
}
