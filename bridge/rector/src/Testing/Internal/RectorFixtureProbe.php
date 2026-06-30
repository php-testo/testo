<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Testing\Internal;

/**
 * Holds the synthetic test method for rule fixtures.
 *
 * A rule's fixtures are files, not methods, but Testo identifies a test by a
 * {@see \ReflectionFunctionAbstract}. {@see Middleware\RectorFixtureFinder} defines one test per
 * rule using this static method as the reflection, and {@see Middleware\RectorFixtureInterceptor}
 * invokes it once per fixture (via the default test handler) with the rule's {@see RectorRunner}
 * and the fixture path as arguments — so it is a real, executed test body, not a placeholder.
 *
 * It is static so the (un-instantiable) rule class, which is the case's reflection, is never
 * constructed.
 *
 * @internal
 * @psalm-internal Testo\Bridge\Rector
 */
final class RectorFixtureProbe
{
    /**
     * @param non-empty-string $path
     */
    public static function fixture(RectorRunner $runner, string $path): void
    {
        $runner->assertConverts($path);
    }
}
