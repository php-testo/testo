<?php

declare(strict_types=1);

namespace Testo\Bridge;

use Testo\Bridge\Vcr\Matcher;
use Testo\Bridge\Vcr\RecordMode;

/**
 * Marks a test (or a whole test case) as replayed through PHP-VCR: HTTP interactions made during the
 * test are recorded to a cassette on first run and replayed from it afterwards, so the test never
 * touches the network again.
 *
 * ```php
 * use Testo\Bridge\VCR;
 * use Testo\Bridge\Vcr\Matcher;
 * use Testo\Bridge\Vcr\RecordMode;
 *
 * #[VCR('github-user', mode: RecordMode::None, match: [Matcher::Method, Matcher::Url, Matcher::Body])]
 * public function testFetchesUser(): void
 * {
 *     $json = \file_get_contents('https://api.github.com/users/roxblnfk');
 *     // ...
 * }
 * ```
 *
 * Placed on a class, it becomes the default for every test in that case; a method-level attribute
 * overrides it.
 *
 * Requires {@see \Testo\Bridge\Vcr\VcrPlugin} to be registered in the suite.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class VCR
{
    /**
     * Request attributes that must match for a recording to be replayed.
     *
     * @var list<Matcher>
     */
    public array $match;

    /**
     * @param string $name Cassette name — the interaction store PHP-VCR reads from and records to.
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
