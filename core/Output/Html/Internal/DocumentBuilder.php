<?php

declare(strict_types=1);

namespace Testo\Output\Html\Internal;

use Testo\Application\Config\RunConfiguration;
use Testo\Bench\Dto\BenchResult;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\RunResult;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Context\TestResult;
use Testo\Core\Log\Level;
use Testo\Core\Log\Message;
use Testo\Core\Log\MessageLog;
use Testo\Core\Value\Status;
use Testo\Core\Value\Summary;
use Testo\Common\Environment;
use Testo\Common\Info;
use Testo\Common\Reflection;
use Testo\Data\MultipleResult;
use Testo\Filter\Group;
use Testo\Retry;

/**
 * Builds the report document — the whole run as one nested array, ready to be encoded.
 *
 * Everything is read off the finished {@see RunResult} and the {@see Recorder}, keyed by run id, so the
 * order events arrived in during execution cannot change the outcome: concurrent tests land in the tree
 * their results already form, not in the order they happened to finish.
 *
 * Determinism is a stated property of the document, which rules out two habits. Maps are emitted in a
 * fixed order rather than in insertion order — status counts follow the {@see Status} enum, channel and
 * metric names are sorted — because insertion order under concurrency is whatever the scheduler did.
 * And nothing is stamped from the clock while building: every time in the document is an offset from the
 * run start, taken from data recorded during the run.
 *
 * Plugin data is read where the plugin already leaves it, guarded by `class_exists` so a project that
 * installed none of them still gets a report: data sets come from {@see MultipleResult}, assertion
 * counts from {@see Summary::$metrics}, benchmarks from the test's own return value, and diffs from an
 * assertion failure that carries both sides.
 *
 * @internal
 */
final readonly class DocumentBuilder
{
    /**
     * Major version of the document. The renderer refuses a major it does not know rather than drawing a
     * half-broken page, so this changes only when a consumer would break.
     */
    public const SCHEMA_VERSION = 1;

    private FailureMapper $failures;

    /**
     * @param int<0, max> $messageLimit Bytes of channel output kept per test; the rest is dropped and
     *        stated as truncated. Output is the one part of a run that has no natural size — a test that
     *        logs a loop can outweigh every other field in the document put together.
     * @param list<non-empty-string> $suiteNames Every configured suite, including the ones that ran
     *        nothing: a suite filtered down to zero tests leaves no trace in the results, and its absence
     *        from the report would read as "there is no such suite".
     */
    public function __construct(
        private Recorder $recorder,
        private RunConfiguration $config = new RunConfiguration(),
        private int $messageLimit = 65536,
        private array $suiteNames = [],
    ) {
        $this->failures = new FailureMapper();
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    public function build(RunResult $result): array
    {
        $channels = [];
        $suites = [];
        foreach ($result as $suite) {
            $suites[] = $this->suite($suite, $channels);
        }

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'generator' => ['name' => 'testo/testo', 'version' => Info::version()],
            'run' => $this->run($result),
            'environment' => $this->environment(),
            'channels' => self::channels($channels),
            'levels' => self::levels(),
            'suites' => $suites,
        ];
    }

    /**
     * Arguments of one call, named after the parameters they were bound to.
     *
     * @return list<array{name: string, type: string, value: string}>
     */
    private static function arguments(TestResult $result): array
    {
        $parameters = $result->info->testDefinition->reflection->getParameters();

        $arguments = [];
        $position = 0;
        foreach ($result->info->arguments as $key => $value) {
            $name = \is_string($key) ? $key : ($parameters[$position]->name ?? (string) $position);
            ++$position;

            $arguments[] = [
                'name' => $name,
                'type' => ValuePrinter::type($value),
                'value' => ValuePrinter::print($value),
            ];
        }

        return $arguments;
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private static function message(Message $message, float $startedAt): array
    {
        $data = [
            # Relative to the run start: an absolute timestamp would make two runs of the same code
            # produce different documents, and a reader compares messages to each other anyway.
            'time' => $message->time - $startedAt,
            'channel' => $message->channel,
            'level' => $message->level->value,
            'content' => $message->content,
        ];

        $message->context === [] or $data['context'] = self::scalarMap($message->context);

        return $data;
    }

    /**
     * Channels the run wrote to, with how many messages each carried. Names only — how a channel looks
     * is the renderer's business, and a document that pinned colours would freeze them into every
     * report ever written.
     *
     * @param array<string, int> $counts
     * @return list<array{name: string, messages: int}>
     */
    private static function channels(array $counts): array
    {
        \ksort($counts, \SORT_STRING);

        $channels = [];
        foreach ($counts as $name => $messages) {
            $channels[] = ['name' => $name, 'messages' => $messages];
        }

        return $channels;
    }

    /**
     * Severity levels, most severe first — the order a level filter offers them in.
     *
     * @return list<string>
     */
    private static function levels(): array
    {
        return \array_map(static fn(Level $level): string => $level->value, Level::cases());
    }

    /**
     * Test counts, every status present including the zeros, in enum order.
     *
     * Insertion order would be whatever order tests finished in, which concurrency makes unstable; the
     * enum order is the same in every report. The zeros are kept on purpose — a filter that offers the
     * full vocabulary needs to know a status exists and counted nothing.
     *
     * @return array<non-empty-string, mixed>
     */
    private static function summary(Summary $summary): array
    {
        $counts = [];
        foreach (Status::cases() as $status) {
            $counts[self::status($status)] = $summary->count($status);
        }

        return [
            'total' => $summary->total(),
            'counts' => $counts,
            'metrics' => self::metrics($summary),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function metrics(Summary $summary): array
    {
        $metrics = $summary->metrics;
        \ksort($metrics, \SORT_STRING);

        return $metrics;
    }

    /**
     * The description as the run ended up with it, which is not always the one in the PHPDoc: it is
     * computed in the pipeline and a plugin may replace it, so the attribute wins over the source.
     */
    private static function description(TestResult $result): ?string
    {
        $description = $result->getAttribute('description');
        \is_string($description) && $description !== '' or $description = null;

        $description ??= $result->info->testDefinition->getDescription();

        return $description === '' ? null : $description;
    }

    /**
     * Why a test is not simply passed or failed — the message of a skip, a cancel or an abort, which is
     * the only place that reason exists.
     */
    private static function statusReason(TestResult $result): ?string
    {
        if ($result->failure === null || $result->status->isFailure()) {
            return null;
        }

        $message = $result->failure->getMessage();

        return $message === '' ? null : $message;
    }

    /**
     * Groups a test belongs to — the union of what the method, its class, the parents and the traits
     * declare, which is the same set `--group` selects on.
     *
     * Read through reflection rather than off the result: {@see Group} is consumed by the filter at
     * discovery time and never lands in a test's attributes, so there is nowhere else to read it from.
     * Sorted, because the union's order follows the traversal and says nothing.
     *
     * @return list<string>
     */
    private static function groups(TestResult $result): array
    {
        if (!\class_exists(Group::class)) {
            return [];
        }

        $info = $result->info;
        $reflections = [$info->testDefinition->reflection];
        $class = $info->caseInfo->definition->reflection;
        $class === null or $reflections[] = $class;

        $groups = [];
        foreach ($reflections as $reflection) {
            $attributes = $reflection instanceof \ReflectionClass
                ? Reflection::fetchClassAttributes($reflection, attributeClass: Group::class)
                : Reflection::fetchFunctionAttributes($reflection, attributeClass: Group::class);

            foreach ($attributes as $attribute) {
                foreach ($attribute->newInstance()->names as $name) {
                    $name === '' or $groups[$name] = true;
                }
            }
        }

        $groups = \array_keys($groups);
        \sort($groups, \SORT_STRING);

        return $groups;
    }

    /**
     * @return array{maxAttempts: int, markFlaky: bool}|null
     */
    private static function retryPolicy(TestResult $result): ?array
    {
        if (!\class_exists(Retry::class)) {
            return null;
        }

        foreach (self::attributeList($result, Retry::class) as $policy) {
            if ($policy instanceof Retry) {
                return ['maxAttempts' => $policy->maxAttempts, 'markFlaky' => $policy->markFlaky];
            }
        }

        return null;
    }

    /**
     * Attributes of one class as the pipeline grouped them: a list per attribute class, since an
     * attribute may repeat on a test.
     *
     * @param class-string|non-empty-string $class
     * @return list<object>
     */
    private static function attributeList(TestResult $result, string $class): array
    {
        /** @var mixed $attributes */
        $attributes = $result->info->getAttribute($class);

        if (\is_object($attributes)) {
            return [$attributes];
        }

        if (!\is_array($attributes)) {
            return [];
        }

        return \array_values(\array_filter($attributes, \is_object(...)));
    }

    /**
     * Anything that cannot survive JSON as itself, stated as a label instead — a report describes the
     * input it was given, and an unserializable value still has a readable form.
     *
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function scalarMap(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = match (true) {
                \is_scalar($value), $value === null => $value,
                \is_array($value) => \array_map(
                    static fn(mixed $item): mixed => \is_scalar($item) || $item === null
                        ? $item
                        : ValuePrinter::print($item),
                    \array_values($value),
                ),
                default => ValuePrinter::print($value),
            };
        }

        return $result;
    }

    /**
     * @return non-empty-string
     */
    private static function status(Status $status): string
    {
        return \strtolower($status->name);
    }

    /**
     * @return non-empty-string
     */
    private static function timestamp(float $microtime): string
    {
        /** @var non-empty-string */
        return \date(\DATE_ATOM, (int) $microtime);
    }

    /**
     * Total wall-clock the given `[start, end]` intervals cover, overlaps counted once.
     *
     * @param list<array{float, float}> $intervals
     */
    private static function union(array $intervals): float
    {
        if ($intervals === []) {
            return 0.0;
        }

        \usort($intervals, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        $total = 0.0;
        [$start, $end] = $intervals[0];
        foreach ($intervals as [$s, $e]) {
            if ($s > $end) {
                # A gap: close the run in progress and open a new one.
                $total += $end - $start;
                $start = $s;
                $end = $e;
            } elseif ($e > $end) {
                $end = $e;
            }
        }

        return $total + ($end - $start);
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private function run(RunResult $result): array
    {
        $phases = [];
        foreach ($result->timing->phases() as $name => $duration) {
            $phases[] = ['name' => $name, 'duration' => $duration];
        }

        # Wall-clock during which at least one test body was running — the union of the intervals the
        # timeline draws, overlaps counted once. Two things fall out of it that summed time alone cannot
        # tell apart: pipeline overhead (`tests` phase minus execution — framework work around and
        # between the bodies), and the concurrency boost (declared work over the wall it truly took).
        $intervals = [];
        $work = 0.0;
        foreach ($result as $suite) {
            foreach ($suite as $case) {
                foreach ($case as $test) {
                    $offset = $this->recorder->offsetOf($test->info->identity);
                    if ($offset === null) {
                        continue;
                    }
                    $duration = $test->summary->duration;
                    $intervals[] = [$offset, $offset + $duration];
                    $work += $duration;
                }
            }
        }

        $execution = self::union($intervals);

        return [
            'status' => self::status($result->status),
            'startedAt' => self::timestamp($this->recorder->startedAt()),
            'duration' => $result->duration(),
            # Summed test time, which exceeds the wall clock when tests ran concurrently. The report says
            # so rather than presenting the difference as an inconsistency.
            'testDuration' => $result->summary->duration,
            'execution' => $execution,
            'overhead' => \max(0.0, $result->timing->tests - $execution),
            'boost' => $execution > 0.0 ? $work / $execution : null,
            'phases' => $phases,
            'summary' => self::summary($result->summary),
        ];
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private function environment(): array
    {
        return [
            'php' => Environment::getPhpVersion(),
            'sapi' => \PHP_SAPI,
            'testo' => Info::version(),
            'os' => Environment::getOs(),
            'cpu' => Environment::getCpu(),
            'cwd' => \getcwd() ?: '',
            'extensions' => [
                'xdebug' => Environment::hasXDebug() ? Environment::getXDebugVersion() : null,
                'pcov' => \extension_loaded('pcov') ? (string) \phpversion('pcov') : null,
                'opcache' => Environment::isOpCacheEnabled()
                    ? (Environment::isJitEnabled() ? 'enabled with JIT' : 'enabled')
                    : null,
            ],
            'config' => [
                'file' => (string) $this->config->configFile,
                'suites' => $this->suiteNames,
                # The effective input, minus the defaults nobody asked for. Environment variables are
                # deliberately absent: a report gets committed and shared, tokens must not travel with it.
                'options' => self::scalarMap($this->config->givenOptions()),
                'arguments' => self::scalarMap($this->config->arguments),
            ],
        ];
    }

    /**
     * @param array<string, int> $channels Message count per channel, accumulated across the walk.
     * @return array<non-empty-string, mixed>
     */
    private function suite(SuiteResult $result, array &$channels): array
    {
        $cases = [];
        $name = null;
        foreach ($result as $case) {
            $cases[] = $this->case($case, $channels, $name);
        }

        return [
            'id' => $name ?? '(empty)',
            'name' => $name ?? '(empty)',
            'status' => self::status($result->status),
            'duration' => $result->summary->duration,
            'summary' => self::summary($result->summary),
            'cases' => $cases,
        ];
    }

    /**
     * @param array<string, int> $channels
     * @param non-empty-string|null $suiteName Filled in from the first test met — a suite result carries
     *        no name of its own, and its tests' addresses are where the name actually lives.
     * @return array<non-empty-string, mixed>
     */
    private function case(CaseResult $result, array &$channels, ?string &$suiteName): array
    {
        $tests = [];
        $name = null;
        $file = null;
        $type = null;

        foreach ($result as $test) {
            $identity = $test->info->identity;
            $suiteName ??= $identity->suite;
            # The class the case groups, or the file when there is no class — a case of free functions
            # groups a file rather than a type. Not `CaseInfo::$name`, which carries a `[type]` suffix
            # meant for a terminal header: the type is already a field of its own here.
            $name ??= $identity->case ?? (string) $identity->file;
            $file ??= (string) $identity->file;
            $type ??= $identity->type;

            $tests[] = $this->test($test, $channels);
        }

        return [
            'id' => ($suiteName ?? '') . '::' . ($name ?? '(empty)'),
            'name' => $name ?? '(empty)',
            'file' => $file ?? '',
            'type' => $type ?? 'test',
            'status' => self::status($result->status),
            'duration' => $result->summary->duration,
            'tests' => $tests,
        ];
    }

    /**
     * @param array<string, int> $channels
     * @return array<non-empty-string, mixed>
     */
    private function test(TestResult $result, array &$channels): array
    {
        $info = $result->info;
        $identity = $info->identity;
        $reflection = $info->testDefinition->reflection;

        $data = [
            'id' => $identity->suite . '::' . $identity->fqn(),
            # The exact string `--filter` takes back, so a reader can rerun what they are looking at.
            'filter' => $identity->fqn(),
            'name' => $info->name,
            'description' => self::description($result),
            'type' => $identity->type,
            'status' => self::status($result->status),
            'groups' => self::groups($result),
            'file' => (string) $identity->file,
            'line' => $reflection->getStartLine() === false ? null : $reflection->getStartLine(),
            'startedAt' => $this->recorder->offsetOf($identity),
            'duration' => $result->summary->duration,
            'metrics' => self::metrics($result->summary),
        ];

        $reason = self::statusReason($result);
        $reason === null or $data['statusReason'] = $reason;

        if ($result->failure !== null) {
            $data['failure'] = $this->failures->map($result->failure, $reflection);
        }

        $attempts = $this->attempts($result, $channels);
        $attempts === null or $data['attempts'] = $attempts;

        $policy = self::retryPolicy($result);
        $policy === null or $data['retryPolicy'] = $policy;

        $dataSets = $this->dataSets($result, $channels);
        $dataSets === null or $data['dataSets'] = $dataSets;

        if (BenchMapper::supports($result->result)) {
            /** @var BenchResult $bench */
            $bench = $result->result;
            $data['bench'] = BenchMapper::map($bench);
        }

        $output = $this->messages($result->messages, $channels);
        $output['messages'] === [] or $data['messages'] = $output['messages'];
        $output['truncated'] === null or $data['truncated'] = ['messages' => $output['truncated']];

        return $data;
    }

    /**
     * Every attempt of a retried test, oldest first, with the kept one last.
     *
     * Null — rather than a single-entry list — when the test ran once: an attempt count is only worth
     * showing where there was more than one, and a list of one would put an "attempts" section on every
     * test in the report.
     *
     * @param array<string, int> $channels
     * @return list<array<non-empty-string, mixed>>|null
     */
    private function attempts(TestResult $result, array &$channels): ?array
    {
        $discarded = $this->recorder->discardedAttemptsOf($result->info->identity);
        if ($discarded === []) {
            return null;
        }

        $attempts = [];
        foreach ($discarded as $attempt) {
            $attempts[] = $this->attempt($attempt['number'], $attempt['result'], false, $channels);
        }

        $attempts[] = $this->attempt(\count($attempts) + 1, $result, true, $channels);

        return $attempts;
    }

    /**
     * @param int<1, max> $number
     * @param array<string, int> $channels
     * @return array<non-empty-string, mixed>
     */
    private function attempt(int $number, TestResult $result, bool $kept, array &$channels): array
    {
        $data = [
            'number' => $number,
            'status' => self::status($result->status),
            'duration' => $result->summary->duration,
            'kept' => $kept,
        ];

        if ($result->failure !== null) {
            $data['failure'] = $this->failures->map($result->failure, $result->info->testDefinition->reflection);
        }

        # A discarded attempt's own output went into a dropped scope, so it usually has none; when it does,
        # it belongs to the attempt rather than to the test that eventually passed.
        $output = $kept ? ['messages' => [], 'truncated' => null] : $this->messages($result->messages, $channels);
        $output['messages'] === [] or $data['messages'] = $output['messages'];

        return $data;
    }

    /**
     * Data sets of one test, each with the arguments it was called with.
     *
     * @param array<string, int> $channels
     * @return list<array<non-empty-string, mixed>>|null
     */
    private function dataSets(TestResult $result, array &$channels): ?array
    {
        if (!\class_exists(MultipleResult::class)) {
            return null;
        }

        $multiple = $result->getAttribute(MultipleResult::class);
        if (!$multiple instanceof MultipleResult) {
            return null;
        }

        $sets = [];
        foreach ($multiple->results as $set) {
            $identity = $set->info->identity;
            $coordinates = $this->recorder->dataSetOf($identity);

            $data = [
                'providerIndex' => $identity->dataProvider,
                'index' => $identity->dataSet ?? 0,
                # Addressed by index, labelled by key: provider keys are free to repeat, so the key is a
                # label and never an address.
                'key' => (string) ($coordinates['key'] ?? ($identity->dataSet ?? 0)),
                'status' => self::status($set->status),
                'duration' => $set->summary->duration,
                'metrics' => self::metrics($set->summary),
                'arguments' => self::arguments($set),
            ];

            if ($set->failure !== null) {
                $data['failure'] = $this->failures->map($set->failure, $set->info->testDefinition->reflection);
            }

            if (BenchMapper::supports($set->result)) {
                /** @var BenchResult $bench */
                $bench = $set->result;
                $data['bench'] = BenchMapper::map($bench);
            }

            $output = $this->messages($set->messages, $channels);
            $output['messages'] === [] or $data['messages'] = $output['messages'];

            $sets[] = $data;
        }

        return $sets;
    }

    /**
     * Channel output of one test, capped at {@see $messageLimit} bytes.
     *
     * @param array<string, int> $channels
     * @return array{
     *     messages: list<array<non-empty-string, mixed>>,
     *     truncated: array{shown: int, total: int, bytes: int, limit: int}|null
     * }
     */
    private function messages(MessageLog $log, array &$channels): array
    {
        $messages = [];
        $bytes = 0;
        $total = \count($log);
        $truncated = false;
        $startedAt = $this->recorder->startedAt();

        foreach ($log as $message) {
            $channels[$message->channel] = ($channels[$message->channel] ?? 0) + 1;
            $bytes += \strlen($message->content);

            if ($truncated) {
                continue;
            }

            if ($bytes > $this->messageLimit) {
                $truncated = true;
                continue;
            }

            $messages[] = self::message($message, $startedAt);
        }

        return [
            'messages' => $messages,
            'truncated' => $truncated
                ? [
                    'shown' => \count($messages),
                    'total' => $total,
                    'bytes' => $bytes,
                    'limit' => $this->messageLimit,
                ]
                : null,
        ];
    }
}
