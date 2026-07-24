<?php

declare(strict_types=1);

namespace Tests\Application\Feature\Messenger;

use Testo\Application\Internal\MessengerHub;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Message;
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Testing\Attribute\TestingSuite;
use Testo\Testing\Helper\TestRunner;
use Testo\Testing\InjectPlugin;
use Tests\Application\Stub\Messenger\Concurrency\MessengerConcurrency;
use Tests\Application\Stub\Messenger\Concurrency\RevoltMessengerScenarios;
use Tests\Application\Stub\Messenger\Concurrency\RoundRobinMessengerScenarios;

/**
 * The messenger keeps each test's message buffer in a fiber-local guard ({@see MessengerHub}). These cases
 * drive stub suites whose tests interleave — on Testo's fiber scheduler and on a real Revolt loop — each
 * logging a distinct set of messages across many suspensions. Every test's captured messages, read back off
 * its {@see TestResult}, must be exactly its own: concurrency must not let one test's output leak into
 * another's buffer.
 */
#[Test]
#[Covers(MessengerHub::class)]
#[TestingSuite(
    path: __DIR__ . '/../../Stub/Messenger/Concurrency',
    plugins: [InjectPlugin::class],
)]
final class ConcurrentMessengerIsolationTest
{
    public function fiberRoundRobinTestsKeepSeparateMessageBuffers(): void
    {
        self::assertOwnMessages(
            TestRunner::runTest([RoundRobinMessengerScenarios::class, 'alphaLogsThreeMessages']),
            'alpha',
            3,
        );
        self::assertOwnMessages(
            TestRunner::runTest([RoundRobinMessengerScenarios::class, 'betaLogsFourMessages']),
            'beta',
            4,
        );
        self::assertOwnMessages(
            TestRunner::runTest([RoundRobinMessengerScenarios::class, 'gammaLogsFiveMessages']),
            'gamma',
            5,
        );
    }

    public function revoltPerCaseTestsKeepSeparateMessageBuffers(): void
    {
        self::assertOwnMessages(
            TestRunner::runTest([RevoltMessengerScenarios::class, 'alphaLogsThreeMessages']),
            'alpha',
            3,
        );
        self::assertOwnMessages(
            TestRunner::runTest([RevoltMessengerScenarios::class, 'betaLogsFourMessages']),
            'beta',
            4,
        );
        self::assertOwnMessages(
            TestRunner::runTest([RevoltMessengerScenarios::class, 'gammaLogsFiveMessages']),
            'gamma',
            5,
        );
    }

    /**
     * The test passed (its own in-fiber isolation check held) and the messages captured onto its result are
     * exactly its own `$prefix`-tagged messages, in order — no sibling's message leaked into the buffer.
     */
    private static function assertOwnMessages(TestResult $result, string $prefix, int $count): void
    {
        Assert::same($result->status, Status::Passed);

        $contents = \array_map(
            static fn(Message $message): string => $message->content,
            $result->messages->channel(MessengerConcurrency::CHANNEL),
        );

        $expected = [];
        for ($i = 1; $i <= $count; $i++) {
            $expected[] = $prefix . '-' . $i;
        }

        Assert::same($contents, $expected);
    }
}
