<?php

declare(strict_types=1);

namespace Tests\Bridge\VCR\Acceptance;

use Testo\Assert;
use Testo\Bridge\VCR\Internal\VcrInterceptor;
use Testo\Bridge\VCR\RecordMode;
use Testo\Bridge\VCR\VcrPlugin;
use Testo\Bridge\VCR;
use Testo\Codecov\Covers;
use Testo\Test;

/**
 * Acceptance test for the VCR bridge. The suite registers {@see VcrPlugin} with a cassette path
 * pointing at `tests/fixtures` (see `bridge/vcr/tests/suites.php`), so a `#[VCR]` test replays the
 * committed cassette instead of touching the network.
 */
#[Test]
#[Covers(VcrPlugin::class)]
#[Covers(VcrInterceptor::class)]
#[Covers(VCR::class)]
final class VcrReplayTest
{
    /**
     * `hello.yml` holds one recorded GET; {@see RecordMode::None} means replay-only, so this proves the
     * bridge serves the recorded body without any real request (the host does not resolve).
     */
    #[VCR('hello.yml', mode: RecordMode::None)]
    public function replaysRecordedResponseWithoutNetwork(): void
    {
        $body = \file_get_contents('https://api.example.test/hello');

        Assert::same($body, '{"message":"hello from cassette"}');
    }
}
