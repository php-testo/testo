<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Testo\Common\EventListenerCollector;
use Testo\Core\Context\Identity;
use Testo\Core\Context\TestResult;
use Testo\Event\Framework\SessionStarting;
use Testo\Event\Test\TestDataSetStarting;
use Testo\Event\Test\TestPipelineStarting;
use Testo\Event\Test\TestRetrying;

/**
 * The part of a run that its results do not keep.
 *
 * A {@see \Testo\Core\Context\RunResult} says what happened; three things it does not say are needed to
 * describe a run in full:
 *
 * - **when** the run and each test began — durations alone cannot draw a timeline, and cannot show that
 *   concurrent tests overlapped;
 * - the **label** of a data set — `TestIdentity` addresses a data set by index, on purpose, because
 *   provider keys are free to repeat, so the key stays on the event;
 * - the **discarded attempts** of a retried test — the retry interceptor returns the last attempt and
 *   drops the rest, leaving only a breadcrumb in the `retry` channel.
 *
 * Everything is keyed by {@see Identity::$runtimeId}: one number per in-flight run, shared by a test's
 * retries (they re-attempt one run) and distinct for every data set, which is exactly the granularity
 * wanted here. Keying by name instead would merge two suites that include the same file.
 *
 * Recording during the run and reading afterwards is the one thing that cannot be derived from the
 * finished result — so the order events arrive in must not leak into the document. It does not: entries
 * are stored per run id and read back by the tree walk, never appended to a shared list.
 *
 * @internal
 */
final class Recorder
{
    /** Wall-clock start of the run, as {@see \microtime()} returns it; null before the session starts. */
    private ?float $startedAt = null;

    /**
     * Offset from the run start at which each test began, keyed by run id.
     *
     * @var array<int, float>
     */
    private array $starts = [];

    /**
     * Data-set coordinates and label, keyed by the data set's own run id.
     *
     * @var array<int, array{key: string|int, provider: int|null, index: int}>
     */
    private array $dataSets = [];

    /**
     * Attempts a retry discarded, in the order they ran, keyed by the test's run id.
     *
     * @var array<int, list<array{number: int<1, max>, result: TestResult}>>
     */
    private array $attempts = [];

    public function configure(EventListenerCollector $listeners): void
    {
        $listeners->addListener(SessionStarting::class, $this->onSessionStarting(...));
        $listeners->addListener(TestPipelineStarting::class, $this->onTestStarting(...));
        $listeners->addListener(TestDataSetStarting::class, $this->onDataSetStarting(...));
        $listeners->addListener(TestRetrying::class, $this->onTestRetrying(...));
    }

    /**
     * Wall-clock start of the run — the origin every offset in the document is measured from.
     *
     * Falls back to now for a reporter that was configured after the session started, so timings degrade
     * to zero-length rather than turning into nonsense far in the past.
     */
    public function startedAt(): float
    {
        return $this->startedAt ??= \microtime(true);
    }

    /**
     * Seconds from the run start to the moment this test began, or null when the test never announced a
     * start — a case the finished result cannot fill in, so the timeline leaves the row out.
     */
    public function offsetOf(Identity $identity): ?float
    {
        return $this->starts[$identity->runtimeId] ?? null;
    }

    /**
     * @return array{key: string|int, provider: int|null, index: int}|null
     */
    public function dataSetOf(Identity $identity): ?array
    {
        return $this->dataSets[$identity->runtimeId] ?? null;
    }

    /**
     * @return list<array{number: int<1, max>, result: TestResult}>
     */
    public function discardedAttemptsOf(Identity $identity): array
    {
        return $this->attempts[$identity->runtimeId] ?? [];
    }

    /**
     * The event carries nothing: its arrival is the timestamp.
     */
    private function onSessionStarting(): void
    {
        $this->startedAt ??= \microtime(true);
    }

    private function onTestStarting(TestPipelineStarting $event): void
    {
        $this->stampStart($event->testInfo->identity);
    }

    private function onDataSetStarting(TestDataSetStarting $event): void
    {
        $identity = $event->testInfo->identity;
        $this->stampStart($identity);

        $this->dataSets[$identity->runtimeId] = [
            'key' => $event->dataSetKey,
            'provider' => $event->providerIndex,
            'index' => $event->datasetIndex,
        ];
    }

    /**
     * The attempt being announced is the *next* one, so the result carried alongside it is the attempt
     * before — the one the interceptor is about to drop.
     */
    private function onTestRetrying(TestRetrying $event): void
    {
        $number = $event->attempt - 1;
        $number >= 1 or $number = 1;

        $this->attempts[$event->testInfo->identity->runtimeId][] = [
            'number' => $number,
            'result' => $event->previousRunResult,
        ];
    }

    private function stampStart(Identity $identity): void
    {
        # First stamp wins: a retried test re-enters the pipeline under the same run id, and the test
        # began when its first attempt did.
        $this->starts[$identity->runtimeId] ??= \microtime(true) - $this->startedAt();
    }
}
