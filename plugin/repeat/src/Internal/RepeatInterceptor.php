<?php

declare(strict_types=1);

namespace Testo\Repeat\Internal;

use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Level;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Repeat;

/**
 * Interceptor that repeats a test execution based on the provided repeat policy.
 *
 * @see Repeat
 *
 * @api
 */
#[InterceptorOptions(order: InterceptorOptions::ORDER_DEFAULT - 190, onConflict: ConflictPolicy::Last)]
final readonly class RepeatInterceptor implements TestRunInterceptor
{
    /**
     * Channel the per-run status symbols are written to (one character per run).
     */
    public const CHANNEL = 'repeat';

    /**
     * Status symbols are accumulated in a string and flushed to the channel at most this often
     * (seconds), so a fast test repeated many times produces a handful of messages, not thousands.
     */
    private const FLUSH_INTERVAL = 0.2;

    public function __construct(
        private Repeat $options,
        private Messenger $messenger,
    ) {}

    #[\Override]
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        $times = $this->options->times;
        $maxFailures = $this->options->maxFailures;

        $failures = 0;

        # A repeated test still counts as one test, but every run does real work — accumulate the
        # runs' summaries (their counts are empty here, so only assertions and duration carry over)
        # and fold them into whichever result is returned.
        $accumulated = new Summary();

        # Per-run status symbols accumulate here and are emitted into the parent scope in batches, so
        # the run line survives the (mostly dropped) per-run forks without a message+dispatch per run.
        $symbols = '';
        $lastFlush = \microtime(true);
        $flush = function (bool $eol) use (&$symbols): void {
            if ($symbols === '') {
                return;
            }

            $this->messenger->log(self::CHANNEL, $eol ? "$symbols\n" : $symbols, Level::Info);
            $symbols = '';
        };

        do {
            /**
             * @var TestResult $result
             * @var callable $commit Persist messages from the test
             */
            [$result, $commit] = $this->messenger->fork(
                static fn(callable $commit): array => [$next($info), $commit],
                holdEvents: true,
            );

            $accumulated = $accumulated->merge($result->summary);

            $symbols .= self::symbol($result->status);
            $now = \microtime(true);
            if ($now - $lastFlush >= self::FLUSH_INTERVAL) {
                $flush(false);
                $lastFlush = $now;
            }

            // Skipped / Cancelled / Aborted — abort immediately regardless of threshold.
            if (!$result->status->isCompleted()) {
                $flush(true);
                $commit();
                return $result->withSummary($accumulated);
            }

            if ($result->status->isFailure() && ++$failures > $maxFailures) {
                $flush(true);
                $commit();
                return $result->withSummary($accumulated);
            }
        } while (--$times > 0);

        $flush(true);

        # Keep the messages of the last run — the one whose result we return; earlier runs are dropped.
        $commit();

        $result = $failures > 0 && $this->options->markFlaky
            ? $result->with(status: Status::Flaky)
            : $result;

        return $result->withSummary($accumulated);
    }

    /**
     * Compact single-character status of one run.
     *
     * @return non-empty-string
     */
    private static function symbol(Status $status): string
    {
        return match ($status) {
            Status::Passed, Status::Flaky => '.',
            Status::Failed => 'F',
            Status::Error => 'E',
            Status::Skipped => 'S',
            Status::Risky => 'R',
            Status::Cancelled => 'C',
            Status::Aborted => 'A',
        };
    }
}
