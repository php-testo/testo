<?php

declare(strict_types=1);

namespace Testo;

use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Spec\Internal\SpecInterceptor;

/**
 * Attach a specification fragment to a test — the *content* of a spec item.
 *
 * Combines BDD, Spec-Driven and TDD workflows: the spec — a user story or a slice of the product
 * specification — lives right next to the test that proves it. At runtime the fragment is published
 * to the {@see SpecInterceptor::CHANNEL} messenger channel, and the {@see \Testo\Spec\SpecPlugin}
 * can render the collected fragments into Markdown files on demand (`--spec` CLI flag).
 *
 * The attribute can sit on a method/function (one test) or on a class (every test in the case
 * inherits it), mirroring {@see Retry} and {@see Repeat}.
 *
 * Headings and numbering live in the companion {@see \Testo\Spec\SpecHeader}: a class-level `SpecHeader` opens a
 * numbered section, and each `Spec` becomes an auto-numbered item under it (`5.1`, `5.2`, …).
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION | \Attribute::TARGET_CLASS)]
#[FallbackInterceptor(SpecInterceptor::class)]
final readonly class Spec implements Interceptable
{
    /** @var list<non-empty-string> */
    public array $tags;

    /**
     * @param non-empty-string $story The user story or specification fragment, written in Markdown. This is the
     *        behaviour the test verifies — keep it human-readable, it ends up verbatim in the report.
     * @param list<non-empty-string> $tags Free-form labels (e.g. a feature key, a Jira id) used to
     *        group or filter fragments in generated documents.
     */
    public function __construct(
        public string $story,
        array $tags = [],
    ) {
        \trim($story) === '' and throw new \InvalidArgumentException('Spec story must not be empty.');

        $this->tags = \array_values(\array_filter(
            $tags,
            static fn(string $tag): bool => $tag !== '',
        ));
    }
}
