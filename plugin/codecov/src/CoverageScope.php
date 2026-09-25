<?php

declare(strict_types=1);

namespace Testo\Codecov;

/**
 * What a test covers, supplied by a plugin rather than declared on the test.
 *
 * A plugin that runs tests with no method of their own to carry `#[Covers]` — a harness driving
 * fixtures through one shared probe, say — attaches the scope to the {@see \Testo\Core\Context\TestInfo}
 * it hands down the pipeline, from an interceptor outer to coverage collection:
 *
 * ```php
 * return $next($info->withAttribute(CoverageScope::class, new CoverageScope(new Covers(SomeRule::class))));
 * ```
 *
 * The scope is a default: `#[Covers]` / `#[CoversNothing]` declared on the test method or its class
 * still win. A test carrying a scope is measured whatever its type, since the plugin attaching it
 * asked for exactly that; `CodecovPlugin`'s `testTypes` selects only the tests that carry none.
 *
 * @api
 */
final readonly class CoverageScope
{
    /** @var list<Covers|CoversNothing> */
    public array $attributes;

    /**
     * @param Covers|CoversNothing ...$attributes The targets the test covers, or one `CoversNothing` to
     *        measure nothing. Mixing the two is rejected, as it is on a test.
     */
    public function __construct(Covers|CoversNothing ...$attributes)
    {
        $attributes === [] and throw new \InvalidArgumentException('A coverage scope needs at least one `Covers` or a `CoversNothing`.');

        $nothing = \array_filter($attributes, static fn(Covers|CoversNothing $attribute): bool => $attribute instanceof CoversNothing);
        $nothing !== [] && \count($nothing) !== \count($attributes) and throw new \InvalidArgumentException(
            'A coverage scope can hold `Covers` targets or `CoversNothing`, not both.',
        );

        $this->attributes = \array_values($attributes);
    }
}
