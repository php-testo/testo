<?php

declare(strict_types=1);

namespace Tests\Application\Stub\SuiteFactoryFixture;

use Testo\Test;

/**
 * A trivial, real test case for {@see \Tests\Application\Unit\Internal\SuiteFactoryTest} to discover —
 * exercises {@see \Testo\Application\Internal\SuiteFactory::create()}'s real file/case discovery path,
 * not just the empty-suite degenerate case {@see \Tests\Application\Feature\Runner\EmptyRunTest} covers.
 */
#[Test]
final class FixtureCase
{
    public function passes(): void {}
}
