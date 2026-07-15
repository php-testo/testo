<?php

declare(strict_types=1);

namespace Tests\Bridge\VCR\Stub;

use Testo\Assert;
use Testo\Bridge\VCR\RecordMode;
use Testo\Bridge\VCR;
use Testo\Test;

/**
 * Stub tests exercised through {@see \Testo\Testing\Helper\TestRunner} by the Feature suite. The Stub
 * directory is not a suite location, so these are not discovered directly; each method is a scenario
 * whose reported {@see \Testo\Core\Value\Status} the Feature test asserts on.
 */
final class VcrScenarios
{
    #[Test]
    #[VCR('hello.yml', mode: RecordMode::None)]
    public function replaysRecordedResponse(): void
    {
        Assert::same(\file_get_contents('https://api.example.test/hello'), '{"message":"hello from cassette"}');
    }

    #[Test]
    #[VCR('hello.yml', mode: RecordMode::None)]
    public function unrecordedRequestInNoneModeFails(): void
    {
        // Path is not on the cassette and RecordMode::None forbids a real request, so php-vcr throws.
        \file_get_contents('https://api.example.test/not-recorded');
    }

    #[Test]
    public function untaggedTestPassesThrough(): void
    {
        // No #[VCR]: the interceptor must pass straight through.
        Assert::true(true);
    }
}
