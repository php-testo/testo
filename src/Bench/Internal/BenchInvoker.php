<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use DragonCode\Benchmark\Benchmark;
use Testo\Bench\BenchWith;
use Testo\Bench\Dto\Line;
use Testo\Bench\Dto\ValueRel;
use Testo\Bench\Exception\BenchWithAttributeMissingException;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\Round;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Dto\Value;
use Testo\Core\Context\TestInfo;

final class BenchInvoker
{
    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(BenchWith::class);
        $attr instanceof BenchWith or throw BenchWithAttributeMissingException::fromTestInfo($info);

        # Current function callable
        // $fn = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
        //     ? $info->testDefinition->reflection->getClosure(null)
        //     : $info->testDefinition->reflection->getClosure($info->caseInfo->instance->getInstance());

        $aliases = ['current'];
        // $functions = [static fn (): mixed => $fn(...$info->arguments)];
        $functions = [self::normalizeCallable(
            $info,
            $info->caseInfo->instance === null
                ? $info->testDefinition->reflection->getNamespaceName()
                : [$info->caseInfo->instance->getInstance(), $info->testDefinition->reflection->getName()]
        )];

        # Collect callables
        foreach ($attr->callables as $k => $callable) {
            $f = self::normalizeCallable($info, $callable);
            $aliases[] = (string) $k;
            $functions[] = $f;
        }

        $iterations = [];
        for ($i = 1; $i <= $attr->iterations; ++$i) {
            $iterations[] = self::runIteration(
                iteration: $i,
                functions: $functions,
                revolutions: $attr->revolutions,
            );
        }

        $result = new BenchResult(
            iterations: $iterations,
            aliases: $aliases,
            explanation: self::explain($aliases, $iterations),
        );

        $summaryTable = \Testo\Bench\Internal\Renderer::table($result);
        $roundsTable = \Testo\Bench\Internal\Renderer::rounds($result);
        echo <<<EOT
            Iterations: {$attr->iterations}
            Revolutions: {$attr->revolutions}

            Summary:
            $summaryTable

            Rounds:
            {$roundsTable}
            EOT;

        return $result;
    }

    /**
     * @param list<Round> $iterations
     * @return list<Line>
     */
    private static function explain(array $aliases, array $iterations): array
    {
        $lines = [];
        $l = [];
        foreach ($iterations as $iteration) {
            foreach ($iteration->cases as $ck => $case) {
                $l[$ck][] = [
                    'mem_max' => $case->memory->max,
                    'time_avg' => $case->time->avg,
                ];
            }
        }

        # Summarize results for each case
        foreach ($l as $k => $v) {
            $timeAvg = \array_sum(\array_column($v, 'time_avg')) / \count($v);
            $l[$k] = [
                'mem_max' => \max(\array_column($v, 'mem_max')),
                'time_avg' => $timeAvg,
                'time_sigma' => \sqrt(\array_sum(\array_map(static fn (array $x) => ($x['time_avg'] - $timeAvg) ** 2, $v)) / \count($v)),
            ];
        }

        # Sort by time ascending
        \uasort($l, static fn (array $a, array $b): int => $a['time_avg'] <=> $b['time_avg']);

        # Calculate relative values
        $baseTime = $l[0]['time_avg'];
        $baseMem = $l[0]['mem_max'];
        $place = 1;

        foreach ($l as $key => $line) {
            $lines[$key] = new Line(
                place: $place++,
                name: $aliases[$key],
                memory: new ValueRel(
                    value: $line['mem_max'],
                    diff: $baseMem > 0 ? ($line['mem_max'] - $baseMem) / $baseMem * 100 : 0.0,
                ),
                time: new ValueRel(
                    value: $line['time_avg'],
                    diff: $baseTime > 0 ? ($line['time_avg'] - $baseTime) / $baseTime * 100 : 0.0,
                ),
                rstdev: $line['time_sigma'] / $line['time_avg'],
            );
        }

        \ksort($lines);

        return $lines;
    }

    /**
     * @param int<1, max> $iteration Current iteration number, starting from 1.
     * @param list<callable> $functions List of callables to benchmark.
     * @param int<1, max> $revolutions Number of revolutions to run for each benchmark.
     *
     * @return Round
     */
    private static function runIteration(
        int $iteration,
        array $functions,
        int $revolutions,
    ): Round {
        $data = Benchmark::make()
            ->iterations($revolutions)
            ->compare(...$functions)
            ->toData();

        $cases = [];
        foreach ($data as $case) {
            $cases[] = new Snap(
                revolutions: $revolutions,
                memory: new Value(
                    min: $case->min->memory,
                    avg: $case->avg->memory,
                    max: $case->max->memory,
                    total: $case->total->memory,
                ),
                time: new Value(
                    min: $case->min->time,
                    avg: $case->avg->time,
                    max: $case->max->time,
                    total: $case->total->time,
                ),
            );
        }

        return new Round(
            iteration: $iteration,
            cases: $cases,
        );
    }

    private static function normalizeCallable(TestInfo $info, callable|array $callable): \Closure
    {
        $fn = $callable(...);
        return static fn (): mixed => $fn(...$info->arguments);
    }
}
