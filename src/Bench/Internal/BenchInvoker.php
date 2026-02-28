<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Bench\BenchWith;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\IterationSet;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Exception\BenchWithAttributeMissingException;
use Testo\Core\Context\TestInfo;

/**
 * @internal
 */
final class BenchInvoker
{
    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(BenchWith::class);
        $attr instanceof BenchWith or throw BenchWithAttributeMissingException::fromTestInfo($info);

        # Current function callable
        $fn = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
            ? $info->testDefinition->reflection->getClosure(null)
            : $info->testDefinition->reflection->getClosure($info->caseInfo->instance->getInstance());

        $aliases = ['current'];
        $functions = [static fn(): mixed => $fn(...$info->arguments)];

        # Collect callables
        foreach ($attr->callables as $k => $callable) {
            $f = self::normalizeCallable($info, $callable);
            $aliases[] = (string) $k;
            $functions[] = $f;
        }

        # Warmup
        if ($attr->warmup > 0) {
            for ($i = 0; $i < $attr->warmup; ++$i) {
                foreach ($functions as $function) {
                    $function();
                }
            }
        }

        $iterations = [];
        for ($i = 1; $i <= $attr->iterations; ++$i) {
            $iterations[] = self::runIteration(
                iteration: $i,
                functions: $functions,
                calls: $attr->calls,
            );
        }

        # Convert iteration-centric data to case-centric for easier analysis
        $cases = self::toCaseSets($iterations, $aliases);

        /** @var list<CaseResult> $results Calculated results */
        $results = \array_map(Calculator::calculate(...), $cases);

        $lines = Explanator::prepareLines($cases, $results);

        $result = new BenchResult(
            cases: $cases,
            results: $results,
            lines: $lines,
        );

        $summaryTable = \Testo\Bench\Internal\Renderer::table($result);
        $itersTable = \Testo\Bench\Internal\Renderer::rounds($result);
        echo <<<EOT
            Results for {$info->name}:
            $summaryTable

            Iterations:
            {$itersTable}

            EOT;

        return $result;
    }

    private static function normalizeCallable(TestInfo $info, callable|array $callable): \Closure
    {
        $fn = $callable(...);
        return static fn(): mixed => $fn(...$info->arguments);
    }

    /**
     * @param int<1, max> $iteration Current iteration number, starting from 1.
     * @param list<callable> $functions List of callables to benchmark.
     * @param int<1, max> $calls Number of calls per iteration.
     */
    private static function runIteration(
        int $iteration,
        array $functions,
        int $calls,
    ): IterationSet {
        $cases = [];
        foreach ($functions as $k => $function) {
            $cases[] = self::runCase($function, $calls);
        }

        return new IterationSet(
            number: $iteration,
            cases: $cases,
        );
    }

    private static function runCase(\Closure $function, int $calls): Snap
    {
        $beforeMem = \memory_get_usage();
        $beforeTime = \hrtime(true);
        for ($i = 0; $i < $calls; ++$i) {
            $function();
        }
        $afterTime = \hrtime(true);
        $afterMem = \memory_get_usage();

        $deltaMem = $afterMem - $beforeMem;
        # Delta time in microseconds
        $deltaTime = ($afterTime - $beforeTime) / 1_000;
        return new Snap(
            calls: $calls,
            memory: $deltaMem,
            time: $deltaTime,
        );
    }

    /**
     * Convert {@see IterationSet} to {@see CaseSet} for easier analysis.
     *
     * @param list<IterationSet> $iterations
     * @return list<CaseSet>
     */
    private static function toCaseSets(array $iterations, array $names): array
    {
        $caseSets = [];
        foreach ($iterations as $iteration) {
            foreach ($iteration->cases as $k => $case) {
                $caseSets[$k][] = $case;
            }
        }

        $result = [];
        foreach ($caseSets as $k => $v) {
            $result[] = new CaseSet(
                name: $names[$k] ?? (string) $k,
                iterations: $v,
            );
        }

        return $result;
    }
}
