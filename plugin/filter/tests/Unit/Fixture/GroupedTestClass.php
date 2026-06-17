<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Fixture;

use Testo\Group;
use Testo\Test;

/**
 * Fixture for {@see \Testo\Filter\Internal\FilterInterceptor} group filtering tests.
 *
 * Class-level group `integration` is inherited by every method. Effective group sets:
 * - dbTest:    integration, db
 * - slowTest:  integration, slow
 * - plainTest: integration
 * - multiTest: integration, db, fast
 */
#[Test]
#[Group('integration')]
final class GroupedTestClass
{
    #[Group('db')]
    public function dbTest(): void {}

    #[Group('slow')]
    public function slowTest(): void {}

    public function plainTest(): void {}

    #[Group('db', 'fast')]
    public function multiTest(): void {}
}
