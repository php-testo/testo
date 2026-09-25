<?php

declare(strict_types=1);

namespace Tests\Bridge\Rector\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Bridge\Rector\Testing\Internal\RectorRunner;
use Testo\Codecov\Covers;
use Testo\Core\Log\Level;
use Testo\Expect;
use Testo\Test;
use Tests\Bridge\Rector\Unit\Fixture\RecordingMessenger;
use Tests\Bridge\Rector\Unit\Fixture\ThrowingRule;

#[Test]
#[Covers(RectorRunner::class)]
final class RectorRunnerTest
{
    private const FIXTURE = __DIR__ . '/Fixture/throwing_rule.php.inc';

    public function aCrashingRuleFailsTheFixtureWithItsError(): void
    {
        $runner = new RectorRunner(new RecordingMessenger(), [ThrowingRule::class]);

        Expect::exception(\RuntimeException::class)
            ->withMessagePattern('/^Rector failed on fixture "throwing_rule\.php\.inc":\n.*rule crashed/');

        $runner->assertConverts(Path::create(self::FIXTURE));
    }

    public function aCrashingRuleLogsItsSystemErrorsAsJson(): void
    {
        $messenger = new RecordingMessenger();
        $runner = new RectorRunner($messenger, [ThrowingRule::class]);

        try {
            $runner->assertConverts(Path::create(self::FIXTURE));
        } catch (\RuntimeException) {
        }

        $logged = \array_values(\array_filter(
            $messenger->logged,
            static fn(array $entry): bool => $entry['channel'] === 'rector-errors.json',
        ));
        Assert::count($logged, 1);
        Assert::same($logged[0]['level'], Level::Error);

        $errors = \json_decode($logged[0]['content'], true, flags: \JSON_THROW_ON_ERROR);
        Assert::count($errors, 1);
        Assert::string($errors[0]['message'])->contains('rule crashed');
    }
}
