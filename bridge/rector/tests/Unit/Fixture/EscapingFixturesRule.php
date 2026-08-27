<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit\Fixture;

use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * A rule whose declared fixtures path climbs out of the project root. Resolving it trips the
 * FixtureResolver containment guard, which is how {@see RectorFixtureInterceptorTest} exercises a
 * setup failure that happens before any fixture runs.
 */
#[TestRectorFixtures('../../../../../../../../../../../../../../../nowhere')]
final class EscapingFixturesRule
{
    public function fixture(): void {}
}
