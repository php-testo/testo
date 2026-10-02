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
use Testo\Core\Value\Status;
use Testo\Test;
use Testo\Test\Internal\TestMetadataInterceptor;
use Tests\Test\Unit\Fixture\TestClassWithMetadata;

#[Test]
#[Covers(TestMetadataInterceptor::class)]
final class TestMetadataInterceptorTest
{
    public function emitsATestMetadataMessagePerAttribute(): void
    {
        $output = self::capture('reports');

        Assert::string($output)->contains('##teamcity[testMetadata');
        Assert::string($output)->contains("testName='reports'");
        Assert::string($output)->contains("name='score'");
        Assert::string($output)->contains("type='number'");
        Assert::string($output)->contains("value='97.3'");
        // The pipeline id groups the message onto the test's flow.
        Assert::string($output)->contains('flowId=');
    }

    public function resolvesARelativeFileAgainstTheTestDirectory(): void
    {
        $output = self::capture('reports');

        // A relative image path becomes absolute, rooted at the fixture's own directory.
        $dir = \dirname((string) (new \ReflectionClass(TestClassWithMetadata::class))->getFileName());
        Assert::string($output)->contains("type='image'");
        Assert::string($output)->contains($dir . \DIRECTORY_SEPARATOR . 'chart.png');
    }

    public function emitsNothingForATestWithoutMetadata(): void
    {
        Assert::same(self::capture('noMetadata'), '');
    }

    /**
     * Runs the interceptor over one fixture method and returns what it wrote to its stream.
     *
     * @param non-empty-string $method
     */
    private static function capture(string $method): string
    {
        $stream = \fopen('php://memory', 'rb+');
        \assert($stream !== false);

        $interceptor = new TestMetadataInterceptor($stream);
        $interceptor->runTest(
            self::infoFor($method),
            static fn(TestInfo $info): TestResult => new TestResult(info: $info, status: Status::Passed),
        );

        \rewind($stream);
        $contents = \stream_get_contents($stream);
        \fclose($stream);

        return $contents === false ? '' : $contents;
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
