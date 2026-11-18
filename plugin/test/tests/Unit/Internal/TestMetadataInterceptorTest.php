<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Metadata\Metadata;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Core\Metadata\MetadataKind;
use Testo\Core\Metric\Memory;
use Testo\Core\Metric\Percent;
use Testo\Core\Metric\Scalar;
use Testo\Core\Metric\Time;
use Testo\Core\Value\Status;
use Testo\Expect;
use Testo\Metric\Metric;
use Testo\Test;
use Testo\Test\Internal\TestMetadataInterceptor;
use Tests\Test\Unit\Fixture\TestClassWithMetadata;

#[Test]
#[Covers(TestMetadataInterceptor::class)]
final class TestMetadataInterceptorTest
{
    public function addsAnEntryPerAttributeInDeclarationOrder(): void
    {
        $metadata = self::run('reportsEveryType')->metadata;

        Assert::same(
            \array_map(static fn(MetadataEntry $entry): string => $entry->name, $metadata->entries),
            ['score', 'count', 'elapsed', 'memory', 'coverage', 'note', 'docs', 'chart', 'log'],
        );
    }

    public function numericTypesBecomeNumbersInTheirUnit(): void
    {
        $entries = self::byName(self::run('reportsEveryType')->metadata);

        self::assertNumber($entries['score'], 97.3, Scalar::Number);
        self::assertNumber($entries['count'], 42, Scalar::Number);
        self::assertNumber($entries['elapsed'], 12.5, Time::Milliseconds);
        self::assertNumber($entries['memory'], 2048, Memory::Bytes);
        self::assertNumber($entries['coverage'], 87.5, Percent::Percent);
    }

    public function stringTypesKeepTheirKind(): void
    {
        $entries = self::byName(self::run('reportsEveryType')->metadata);

        Assert::same($entries['note']->kind, MetadataKind::Text);
        Assert::same($entries['note']->value, 'see chart.png');
        // A link is a URL, never a file, so a relative-looking value is not resolved.
        Assert::same($entries['docs']->kind, MetadataKind::Link);
        Assert::same($entries['docs']->value, 'docs/index.html');
        Assert::same($entries['chart']->kind, MetadataKind::Image);
        Assert::same($entries['log']->kind, MetadataKind::Artifact);
    }

    public function resolvesARelativeFileAgainstTheTestDirectory(): void
    {
        $entries = self::byName(self::run('reportsEveryType')->metadata);
        $dir = \dirname((string) (new \ReflectionClass(TestClassWithMetadata::class))->getFileName());

        Assert::same($entries['chart']->value, $dir . \DIRECTORY_SEPARATOR . 'chart.png');
        Assert::same(
            $entries['log']->value,
            $dir . \DIRECTORY_SEPARATOR . 'logs' . \DIRECTORY_SEPARATOR . 'run.log',
        );
    }

    public function keepsAUrlAndAnAbsolutePathAsIs(): void
    {
        $entries = self::byName(self::run('reportsAbsoluteLocations')->metadata);

        Assert::same($entries['remote']->value, 'https://example.com/chart.png');
        Assert::same($entries['unix']->value, '/var/log/run.log');
        Assert::same($entries['windows']->value, 'C:\\logs\\run.log');
    }

    public function appendsToMetadataTheResultAlreadyCarries(): void
    {
        $preset = MetadataEntry::text('earlier', 'kept');

        $metadata = self::run('reportsEveryType', new Metadata([$preset]))->metadata;

        Assert::count($metadata, 10);
        Assert::same($metadata->entries[0], $preset);
    }

    public function rejectsANonNumericValueForANumericType(): never
    {
        Expect::exception(\InvalidArgumentException::class)
            ->withMessageContaining('`speed`')
            ->withMessageContaining('`ms`')
            ->withMessageContaining('`fast`');

        self::run('reportsANonNumericNumber');
    }

    public function leavesTheResultUntouchedForATestWithoutMetadata(): void
    {
        $result = null;
        $next = static function (TestInfo $info) use (&$result): TestResult {
            return $result = new TestResult(info: $info, status: Status::Passed);
        };

        $returned = (new TestMetadataInterceptor())->runTest(self::infoFor('noMetadata'), $next);

        Assert::same($returned, $result);
        Assert::true($returned->metadata->isEmpty());
    }

    private static function assertNumber(MetadataEntry $entry, int|float $value, object $unit): void
    {
        Assert::same($entry->kind, MetadataKind::Number);
        Assert::instanceOf($entry->value, Metric::class);
        Assert::same($entry->value->value, $value);
        Assert::same($entry->value->unit, $unit);
    }

    /**
     * @return array<non-empty-string, MetadataEntry>
     */
    private static function byName(Metadata $metadata): array
    {
        $entries = [];
        foreach ($metadata as $entry) {
            $entries[$entry->name] = $entry;
        }

        return $entries;
    }

    /**
     * Runs the interceptor over one fixture method whose body yields a passed result carrying `$preset`.
     *
     * @param non-empty-string $method
     */
    private static function run(string $method, Metadata $preset = new Metadata()): TestResult
    {
        return (new TestMetadataInterceptor())->runTest(
            self::infoFor($method),
            static fn(TestInfo $info): TestResult => (new TestResult(info: $info, status: Status::Passed))
                ->withMetadata($preset),
        );
    }

    /**
     * @param non-empty-string $method
     */
    private static function infoFor(string $method): TestInfo
    {
        $reflection = new \ReflectionClass(TestClassWithMetadata::class);

        return new TestInfo(
            name: $method,
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Test/Unit'),
                definition: new CaseDefinition(
                    name: TestClassWithMetadata::class,
                    type: 'test',
                    file: Path::create((string) $reflection->getFileName()),
                    reflection: $reflection,
                ),
            ),
            testDefinition: new TestDefinition(new \ReflectionMethod(TestClassWithMetadata::class, $method)),
        );
    }
}
