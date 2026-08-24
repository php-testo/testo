<?php

declare(strict_types=1);

namespace Tests\Bridge\VCR\Self;

use Testo\Assert;
use Testo\Bridge\VCR\Internal\VcrInterceptor;
use Testo\Bridge\VCR\RecordMode;
use Testo\Bridge\VCR\VcrPlugin;
use Testo\Bridge\VCR;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * A class-level {@see VCR} attribute is the default cassette for every test in the case. Both methods
 * replay the same recording, which also proves the exclusive window is released and re-acquired
 * cleanly between sequential VCR tests.
 */
#[Test]
#[Covers(VcrPlugin::class)]
#[Covers(VcrInterceptor::class)]
#[Covers(VCR::class)]
#[VCR('hello.yml', mode: RecordMode::None)]
final class ClassLevelCassetteTest
{
    public function firstMethodUsesClassCassette(): void
    {
        Assert::same(\file_get_contents('https://api.example.test/hello'), '{"message":"hello from cassette"}');
    }

    public function secondMethodUsesClassCassette(): void
    {
        Assert::same(\file_get_contents('https://api.example.test/hello'), '{"message":"hello from cassette"}');
    }

    /**
     * A method-level {@see VCR} overrides the class-level default (ConflictPolicy::Last), so this
     * replays `other.yml` rather than the class's `hello.yml`.
     */
    #[VCR('other.yml', mode: RecordMode::None)]
    public function methodLevelOverridesClassDefault(): void
    {
        Assert::same(\file_get_contents('https://api.example.test/other'), '{"message":"override"}');
    }
}
