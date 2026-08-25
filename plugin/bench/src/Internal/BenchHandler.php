<?php

declare(strict_types=1);

namespace Testo\Bench\Internal;

use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\AssertionSuccess;
use Testo\Bench;
use Testo\Bench\Dto\BenchResult;
use Testo\Bench\Dto\CaseResult;
use Testo\Bench\Dto\CaseSet;
use Testo\Bench\Dto\IterationSet;
use Testo\Bench\Dto\Snap;
use Testo\Bench\Exception\BenchAttributeMissingException;
use Testo\Common\Messenger;
use Testo\Core\Context\TestInfo;
use Testo\Core\Log\Level;

/**
 * @internal
 */
final readonly class BenchHandler
{
    public function __construct(
        private Messenger $messenger,
    ) {}

    public function __invoke(TestInfo $info): mixed
    {
        $attr = $info->getAttribute(Bench::class);
        $attr instanceof Bench or throw BenchAttributeMissingException::fromTestInfo($info);

        # Current function callable
        $fn = $info->caseInfo->instance === null || $info->testDefinition->reflection->isStatic()
            ? $info->testDefinition->reflection->getClosure()
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
        $summaryRecommendations = \Testo\Bench\Internal\Renderer::recommendations($result);
        $itersTable = \Testo\Bench\Internal\Renderer::rounds($result);

        $this->messenger->log('bench-result', <<<EOT
            Results for {$info->name}:
            $summaryTable
            EOT);

        $summaryRecommendations === '' or $this->messenger->log(
            'bench-result',
            $summaryRecommendations,
            level: Level::Warning,
        );

        $this->messenger->log('bench-iterations', <<<EOT
            Iterations:
            {$itersTable}

            EOT);

        self::assertCurrentIsFastest($results, $aliases, $attr->tolerance);

        return $result;
    }

    /**
     * Records the benchmark's verdict as an assertion on the current test: `current` (always case 0)
     * should be the fastest, with `$tolerance` headroom over the fastest callable for noise. A pass
     * records a successful assertion — which also keeps the benchmark from being reported as Risky for
     * asserting nothing; a miss throws, and the runner turns the assertion failure into a Failed test.
     *
     * No-op without the Assert plugin (the {@see \class_exists()} guard), so `testo/bench` stays
     * independent of it.
     *
     * @param list<CaseResult> $results Case results, ordered as measured — index 0 is `current`.
     * @param list<string> $aliases Case aliases in the same order; `$aliases[0]` is `current`.
     */
    private static function assertCurrentIsFastest(array $results, array $aliases, float $tolerance): void
    {
        if (!\class_exists(StaticState::class)) {
            return;
        }

        $record = self::benchmarkVerdict($results, $aliases, $tolerance);

        $state = StaticState::current();
        $state === null or $state->history[] = $record;

        $record instanceof AssertionException and throw $record;
    }

    /**
     * The benchmark's verdict as an assertion record: a successful one when `current` (always case 0)
     * is the fastest within `$tolerance` headroom over the fastest callable, a failing one naming the
     * faster callable otherwise. A `$tolerance` of `\INF` always passes.
     *
     * @param list<CaseResult> $results Case results, ordered as measured — index 0 is `current`.
     * @param list<string> $aliases Case aliases in the same order; `$aliases[0]` is `current`.
     */
    public static function benchmarkVerdict(
        array $results,
        array $aliases,
        float $tolerance,
    ): AssertionSuccess|AssertionException {
        $current = $results[0]->favg;
        $fastest = $current;
        $fastestAlias = $aliases[0];
        foreach ($results as $i => $result) {
            if ($result->favg < $fastest) {
                $fastest = $result->favg;
                $fastestAlias = $aliases[$i];
            }
        }

        $assertion = \sprintf('is the fastest within %.0f%%', $tolerance * 100);

        if ($current <= $fastest * (1 + $tolerance)) {
            return new AssertionSuccess('current', $assertion, '');
        }

        $slower = $fastest > 0.0 ? ($current / $fastest - 1) * 100 : \INF;

        return new AssertionException(
            value: 'current',
            assertion: $assertion,
            context: '',
            reason: \sprintf("'%s' is %.1f%% faster", $fastestAlias, $slower),
            details: '',
        );
    }

    private static function normalizeCallable(TestInfo $info, callable|array $callable): \Closure
    {
        # A `[class-string, method]` pair may name a non-public method, which the plain first-class
        # callable syntax cannot invoke; resolve those through reflection. An `[object, method]` pair
        # and every other callable already close over their target, so keep them as is.
        if (\is_array($callable) && \is_string($callable[0])) {
            $case = $info->caseInfo->instance;
            $instance = $case !== null && $case->hasInstance() ? $case->getInstance() : null;
            $fn = self::methodClosure($callable[0], $callable[1], $instance);
        } else {
            $fn = $callable(...);
        }

        return static fn(): mixed => $fn(...$info->arguments);
    }

    /**
     * Resolves a `[class-string, method]` reference to a closure, bypassing visibility so a benchmark
     * can compare non-public implementations. A static method binds to no instance; a non-static one
     * binds to `$instance`, which must be an instance of the method's declaring class.
     *
     * @param class-string $class
     * @param non-empty-string $method
     */
    public static function methodClosure(string $class, string $method, ?object $instance): \Closure
    {
        $reflection = new \ReflectionMethod($class, $method);

        if ($reflection->isStatic()) {
            return $reflection->getClosure();
        }

        $instance !== null && $reflection->getDeclaringClass()->isInstance($instance)
            or throw new \InvalidArgumentException(
                "Cannot benchmark non-static method {$class}::{$method}() without an instance of {$class}.",
            );

        return $reflection->getClosure($instance);
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
        foreach ($functions as $function) {
            $cases[] = self::runCase($function, $calls);
        }

        return new IterationSet(
            number: $iteration,
            cases: $cases,
        );
    }

    private static function runCase(\Closure $function, int $calls): Snap
    {
        # Peak rather than end-to-end delta: a function that frees what it allocated ends where it
        # started, so the difference would be zero no matter how much it allocated in between. The
        # peak is reset first so it reflects this iteration only, and the collection cycle keeps
        # garbage from a previous case out of the window.
        \gc_collect_cycles();
        \memory_reset_peak_usage();
        $beforeMem = \memory_get_peak_usage();
        $beforeTime = \hrtime(true);
        for ($i = 0; $i < $calls; ++$i) {
            $function();
        }
        $afterTime = \hrtime(true);
        $afterMem = \memory_get_peak_usage();

        $deltaMem = \max(0, $afterMem - $beforeMem);
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
