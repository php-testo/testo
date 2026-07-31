<?php

declare(strict_types=1);

namespace Tests\Filter\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Filter;
use Testo\Filter\DataPointer;
use Testo\Filter\Internal\FilterInterceptor;
use Testo\Test;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Tests\Filter\Unit\Fixture\GroupedTestClass;

/**
 * Verifies that the `name:provider:dataset` filter syntax is parsed and threaded through
 * {@see FilterInterceptor}: Stage 2 records a {@see DataPointer} for the matched test, and
 * Stage 3 ({@see FilterInterceptor::runTest()}) injects it into the {@see TestInfo} attributes.
 */
#[Test]
#[Covers(FilterInterceptor::class)]
#[Covers(DataPointer::class)]
final class DataPointerTest
{
    public function providerAndDatasetIndicesAreInjected(): void
    {
        $pointer = $this->runMatched('dbTest:1:2');

        Assert::instanceOf($pointer, DataPointer::class);
        Assert::same($pointer->provider, 1);
        Assert::same($pointer->dataset, 2);
    }

    public function datasetIndexIsOptional(): void
    {
        $pointer = $this->runMatched('dbTest:3');

        Assert::instanceOf($pointer, DataPointer::class);
        Assert::same($pointer->provider, 3);
        Assert::null($pointer->dataset);
    }

    public function noIndicesMeansNoPointer(): void
    {
        Assert::null($this->runMatched('dbTest'));
    }

    /**
     * Run Stage 2 (locator) to record pointers, then Stage 3 (runTest) for the matched test,
     * returning the {@see DataPointer} injected into the resulting {@see TestInfo}, if any.
     */
    private function runMatched(string $filterName): ?DataPointer
    {
        $path = (new \ReflectionClass(GroupedTestClass::class))->getFileName();
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        (new TestoAttributesLocatorInterceptor())
            ->locateTestCases($definition, static fn(FileDefinitions $f): CaseDefinitions => $f->cases);

        $interceptor = new FilterInterceptor(new Filter(names: [$filterName]));

        # Stage 2: records the reflection -> DataPointer mapping.
        $cases = $interceptor->locateTestCases($definition, static fn(FileDefinitions $f): CaseDefinitions => $f->cases);

        $test = $this->firstTest($cases);

        # Stage 3: inject the pointer (if any) into TestInfo.
        $captured = null;
        $interceptor->runTest(
            $this->makeTestInfo($test),
            static function (TestInfo $info) use (&$captured): TestResult {
                $captured = $info->getAttribute(DataPointer::class);
                return new TestResult($info, Status::Passed);
            },
        );

        return $captured;
    }

    private function firstTest(CaseDefinitions $cases): TestDefinition
    {
        foreach ($cases->getCases() as $case) {
            foreach ($case->tests->getTests() as $test) {
                return $test;
            }
        }

        Assert::fail('Filter matched no test.');
    }

    private function makeTestInfo(TestDefinition $test): TestInfo
    {
        $reflection = $test->reflection;
        \assert($reflection instanceof \ReflectionMethod);

        return new TestInfo(
            name: $reflection->getName(),
            caseInfo: new CaseInfo(
                suiteIdentity: new SuiteIdentity('Filter/Unit'),
                definition: new \Testo\Core\Definition\CaseDefinition(
                    name: $reflection->getDeclaringClass()->getName(),
                    type: 'test',
                    file: Path::create(__FILE__),
                    reflection: $reflection->getDeclaringClass(),
                ),
            ),
            testDefinition: $test,
        );
    }
}
