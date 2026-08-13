<?php

declare(strict_types=1);

namespace Testo\Fiber\Internal;

use Testo\Core\Context\TestResult;
use Testo\Fiber\Exception\CompositeException;
use Testo\Fiber\Schedule;

/**
 * Drives a case's test handlers on Testo's cooperative fiber {@see Scheduler}.
 *
 * An invokable runner — set on {@see \Testo\Core\Context\CaseInfo::$batchRunner} by
 * {@see RunInFiberInterceptor::runTestCase()}. Spawns each handler as a task of a fresh scheduler and
 * drives the whole set per the case {@see Schedule} (`Solo` to completion, or `RoundRobin` / `Random`
 * interleaved). Each handler runs its test's pipeline synchronously inside the fiber, so Testo's
 * fiber-aware guards cooperate and per-test state stays isolated across an interleave.
 *
 * @internal
 * @psalm-internal Testo\Fiber
 */
final readonly class FiberTestBatchRunner
{
    public function __construct(
        private Schedule $schedule,
    ) {}

    /**
     * @param list<callable(): TestResult> $handlers
     * @return list<TestResult>
     */
    public function __invoke(array $handlers): array
    {
        $scheduler = new Scheduler($this->schedule);
        $tasks = \array_map(
            static fn(callable $handler): Task => $scheduler->spawn($handler(...)),
            $handlers,
        );

        $scheduler->drive();

        // Handlers never throw (a pipeline failure is captured as an Aborted result), so an error here is
        // unexpected; surface all of them together rather than dropping every failure but the first.
        $errors = [];
        foreach ($tasks as $i => $task) {
            $task->error === null or $errors[$i] = $task->error;
        }
        $errors === [] or throw new CompositeException($errors);

        return \array_map(
            /** @var TestResult */
            static fn(Task $task): TestResult => $task->result,
            $tasks,
        );
    }
}
