<?php

declare(strict_types=1);

namespace Testo\Output\Json\Internal;

use Testo\Core\Context\RunResult;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Data\MultipleResult;
use Testo\Output\Rendering\BenchMapper;
use Testo\Output\Rendering\StackTrace;

/**
 * Builds a minimalistic JSON report from a {@see RunResult}.
 *
 * The report is intentionally lean: a top-level run summary plus a flat list of
 * failed tests ({@see Status::Failed}/{@see Status::Error}) with everything needed
 * to locate and fix a failure — the throwable, its `previous` chain, the stack
 * trace, and any output the test captured. Passing/skipped/risky tests are
 * collapsed into the counts and never listed individually, which keeps the
 * payload small enough to feed straight into an LLM agent reading stdout.
 *
 * @internal
 */
final class JsonReport
{
    public function generate(RunResult $result): string
    {
        $payload = [
            'status' => self::statusName($result->status),
            'duration' => $result->duration(),
            'totals' => self::totals($result->summary),
            'failures' => self::failures($result),
        ];

        # Benchmarks are the one test kind whose result *is* data: a green status says nothing about
        # what was measured. Omitted entirely when the run had none, so an ordinary run's payload is
        # unchanged.
        $benchmarks = self::benchmarks($result);
        $benchmarks === [] or $payload['benchmarks'] = $benchmarks;

        # JSON_INVALID_UTF8_SUBSTITUTE: failure messages, traces and captured output are raw strings
        # from user code and may contain malformed UTF-8 (binary stdout, non-UTF-8 encodings). Without
        # it JSON_THROW_ON_ERROR would abort the whole report; instead bad bytes become U+FFFD.
        return \json_encode(
            $payload,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE
            | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Test counts keyed by lowercased {@see Status} name, with the grand total first.
     * Zero-count statuses are omitted to keep the object compact.
     *
     * @return array<non-empty-string, int<0, max>>
     */
    private static function totals(Summary $summary): array
    {
        $totals = ['total' => $summary->total()];
        foreach ($summary->counts as $name => $count) {
            if ($count > 0) {
                $totals[\strtolower($name)] = $count;
            }
        }

        return $totals;
    }

    /**
     * Walks the result tree and collects every benchmark measurement in encounter order.
     *
     * A repeatable `#[Bench]` puts each scenario in its own data set, so a benchmark test contributes
     * either one entry (single attribute) or one per set, addressed by its data-set coordinates the way
     * `--filter=method:1:0` names it.
     *
     * @return list<array<non-empty-string, mixed>>
     */
    private static function benchmarks(RunResult $result): array
    {
        $benchmarks = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    foreach (self::benchmarksOf($test) as $benchmark) {
                        $benchmarks[] = $benchmark;
                    }
                }
            }
        }

        return $benchmarks;
    }

    /**
     * @return list<array<non-empty-string, mixed>>
     */
    private static function benchmarksOf(TestResult $test): array
    {
        if (BenchMapper::supports($test->result)) {
            return [['test' => self::testId($test)] + BenchMapper::map($test->result)];
        }

        $multiple = \class_exists(MultipleResult::class)
            ? $test->getAttribute(MultipleResult::class)
            : null;
        if (!$multiple instanceof MultipleResult) {
            return [];
        }

        $benchmarks = [];
        foreach ($multiple->results as $set) {
            if (!BenchMapper::supports($set->result)) {
                continue;
            }

            $identity = $set->info->identity;
            $benchmarks[] = [
                'test' => self::testId($set),
                'dataProvider' => $identity->dataProvider,
                'dataSet' => $identity->dataSet,
            ] + BenchMapper::map($set->result);
        }

        return $benchmarks;
    }

    /**
     * Walks the result tree and collects every failed/errored test in encounter order.
     *
     * @return list<array<non-empty-string, mixed>>
     */
    private static function failures(RunResult $result): array
    {
        $failures = [];
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $test->status->isFailure() and $failures[] = self::failure($test);
                }
            }
        }

        return $failures;
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private static function failure(TestResult $test): array
    {
        $data = [
            'test' => self::testId($test),
            'status' => self::statusName($test->status),
        ];

        $failure = $test->failure;
        if ($failure !== null) {
            $data['exception'] = $failure::class;
            $data['message'] = $failure->getMessage();
            $data['file'] = $failure->getFile();
            $data['line'] = $failure->getLine();
            $data['trace'] = self::trace($failure, $test->info->testDefinition->reflection);

            $causedBy = self::causedBy($failure);
            $causedBy === [] or $data['causedBy'] = $causedBy;
        }

        $output = self::output($test->messages);
        $output === [] or $data['output'] = $output;

        return $data;
    }

    /**
     * Fully-qualified test identifier: `Class::method` for class-bound tests,
     * the function FQN for free-function tests.
     *
     * @return non-empty-string
     */
    private static function testId(TestResult $test): string
    {
        $info = $test->info;
        $class = $info->caseInfo->definition->reflection?->getName();

        return $class !== null
            ? "{$class}::{$info->name}"
            : $info->testDefinition->reflection->getName();
    }

    /**
     * Stack trace as a list of `#n file(line): call` frames, trimmed at the test
     * function boundary so it stops at user code instead of trailing off into
     * dozens of internal runner/pipeline frames — the bulk of which are noise for
     * anyone (or anything) trying to fix the test.
     *
     * @return list<non-empty-string>
     */
    private static function trace(\Throwable $failure, \ReflectionFunctionAbstract $boundary): array
    {
        $frames = StackTrace::cutStackTrace($failure->getTrace(), $boundary);

        $lines = [];
        foreach ($frames as $i => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = isset($frame['line']) ? ":{$frame['line']}" : '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $function = $frame['function'] ?? '';

            $location = $file === '[internal function]' ? $file : "{$file}{$line}";
            $call = $class !== '' ? "{$class}{$type}{$function}()" : "{$function}()";
            $lines[] = "#{$i} {$location}: {$call}";
        }

        return $lines;
    }

    /**
     * The `previous` chain of a throwable, outermost cause first. Each link carries
     * just its location — the outermost trace already covers the call path.
     *
     * @return list<array<non-empty-string, mixed>>
     */
    private static function causedBy(\Throwable $failure): array
    {
        $chain = [];
        $current = $failure->getPrevious();
        while ($current !== null) {
            $chain[] = [
                'exception' => $current::class,
                'message' => $current->getMessage(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
            ];
            $current = $current->getPrevious();
        }

        return $chain;
    }

    /**
     * Test-captured messages (stdout, logs, custom channels) in recorded order.
     *
     * Consecutive messages on the same channel are coalesced into a single block — their contents
     * are concatenated — so a test that prints many lines to stdout yields one `stdout` entry
     * instead of one per line. A switch to a different channel starts a new block.
     *
     * @return list<array{channel: non-empty-string, content: non-empty-string}>
     */
    private static function output(MessageLog $messages): array
    {
        /** @var list<array{channel: non-empty-string, content: non-empty-string}> $output */
        $output = [];
        foreach ($messages as $message) {
            $last = \count($output) - 1;
            if ($last >= 0 && $output[$last]['channel'] === $message->channel) {
                $output[$last]['content'] .= $message->content;
                continue;
            }

            $output[] = ['channel' => $message->channel, 'content' => $message->content];
        }

        # array_values: in-place content mutation above makes Psalm widen the list back to a
        # keyed array; the keys are already 0..n, this just restores the list<> shape.
        return \array_values($output);
    }

    /**
     * @return non-empty-string
     */
    private static function statusName(Status $status): string
    {
        return \strtolower($status->name);
    }
}
