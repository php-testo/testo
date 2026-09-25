<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Internal\Path;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\QuietMessenger;
use Tests\Bridge\Rector\Unit\Fixture\ThrowingRule;

#[Test]
#[Covers(RectorRunner::class)]
final class RectorRunnerTest
{
    public function aCrashingRuleFailsTheFixtureWithItsError(): void
    {
        $runner = new RectorRunner(new QuietMessenger(), [ThrowingRule::class]);

        Expect::exception(\RuntimeException::class)
            ->withMessageContaining('Rector failed on fixture "throwing_rule.php.inc"')
            ->withMessageContaining('rule crashed');

        $runner->assertConverts(Path::create(__DIR__ . '/Fixture/throwing_rule.php.inc'));
    }
}
