<?php

declare(strict_types=1);

namespace Testo\Bridge;

/**
 * Marks a test (or a whole test case) as replayed through PHP-VCR: HTTP interactions made during the
 * test are recorded to a cassette on first run and replayed from it afterwards, so the test never
 * touches the network again.
 *
 * ```php
 * use Testo\Bridge\VCR;
 *
 * #[VCR('github-user')]
 * public function testFetchesUser(): void
 * {
 *     $json = \file_get_contents('https://api.github.com/users/roxblnfk');
 *     // ...
 * }
 * ```
 *
 * Placed on a class, it becomes the default cassette for every test in that case; a method-level
 * attribute overrides it.
 *
 * Requires {@see \Testo\Bridge\Vcr\VcrPlugin} to be registered in the suite.
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class VCR
{
    /**
     * @param string $name Cassette name. The interaction store PHP-VCR reads from and records to.
     */
    public function __construct(
        public string $name,
    ) {}
}
