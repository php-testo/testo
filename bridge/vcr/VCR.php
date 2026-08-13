<?php

declare(strict_types=1);

namespace Testo\Bridge;

use Testo\Bridge\VCR\Internal\VcrInterceptor;
use Testo\Bridge\VCR\Matcher;
use Testo\Bridge\VCR\RecordMode;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;

/**
 * Marks a test (or a whole test case) as replayed through PHP-VCR: HTTP interactions made during the
 * test are recorded to a cassette on first run and replayed from it afterwards, so the test never
 * touches the network again.
 *
 * ```php
 *  use Testo\Bridge\VCR;
 *  use Testo\Bridge\VCR\Matcher;
 *  use Testo\Bridge\VCR\RecordMode;
 *
 *  #[VCR('github-user', mode: RecordMode::None, match: [Matcher::Method, Matcher::Url, Matcher::Body])]
 *  public function testFetchesUser(): void
 *  {
 *      $json = \file_get_contents('https://api.github.com/users/roxblnfk');
 *      // ...
 *  }
 * ```
 *
 * Placed on a class, it becomes the default for every test in that case; a method-level attribute
 * overrides it.
 *
 * **A VCR-tagged test runs as an exclusive, synchronous block.** PHP-VCR is process-global (one active
 * cassette for the whole process), so while the cassette is inserted the test does not yield to the
 * fiber scheduler and no other test may run concurrently — the window is driven to completion and
 * locked. Consequently a `#[VCR]` test must be synchronous: awaiting real async work inside it is
 * unsupported, and two `#[VCR]` tests can never overlap.
 *
 * The attribute is self-wiring: it is {@see Interceptable}, so {@see VcrInterceptor} is inserted into
 * the pipeline (at its own order) only for tests that carry it — no plugin registration needed.
 * Register {@see \Testo\Bridge\VCR\VcrPlugin} only to point php-vcr at a non-default cassette path.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
#[FallbackInterceptor(VcrInterceptor::class)]
final readonly class VCR implements Interceptable
{
    /**
     * Request attributes that must match for a recording to be replayed.
     *
     * @var list<Matcher>
     */
    public array $match;

    /**
     * @param non-empty-string $name Cassette name — the interaction store PHP-VCR reads from and
     *        records to. Required.
     * @param RecordMode|null $mode Record mode for this test; `null` inherits php-vcr's global default
     *        ({@see RecordMode::NewEpisodes}).
     * @param list<Matcher> $match Request matchers for this test; an empty list inherits php-vcr's
     *        default (method + URL).
     */
    public function __construct(
        public string $name,
        public ?RecordMode $mode = null,
        array $match = [],
    ) {
        $this->match = \array_values($match);
    }
}
