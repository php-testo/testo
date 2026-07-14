<?php

declare(strict_types=1);

namespace Tests\Bridge\Vcr\Self;

use Testo\Assert;
use Testo\Bridge\Vcr\Internal\VcrInterceptor;
use Testo\Bridge\Vcr\RecordMode;
use Testo\Bridge\Vcr\VcrPlugin;
use Testo\Bridge\VCR;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * A class-level {@see VCR} attribute is the default cassette for every test in the case. Both methods
 * replay the same recording, which also proves the exclusive window is released and re-acquired
 * cleanly between sequential VCR tests.
 */
#[Test]
#[Covers(VcrPlugin::class, VcrInterceptor::class, VCR::class)]
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
}
