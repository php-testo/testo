<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Value\Status;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Internal\AttributesInterceptor;
use Testo\Pipeline\InterceptorProvider;
use Testo\Pipeline\Middleware\TestRunInterceptor;
use Testo\Pipeline\Middleware\TestCaseRunInterceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Pipeline;
use Testo\Test;
use Internal\Container\ObjectContainer;

#[Test]
#[Covers(AttributesInterceptor::class)]
final class AttributesInterceptorTest
{
    public function runTestWithNoClassOrMethodAttributesCallsNext(): void
    {
        $testInfo = $this->makeTestInfo(TestWithoutAttributes::class, classReflection: false);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $result = $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $testInfo);
        Assert::same($result, $terminal);
        Assert::same($captured->attributes, []);
    }

    public function runTestCallsNextWhenClassHasNoInterceptableAttributes(): void
    {
        $testInfo = $this->makeTestInfo(TestWithoutAttributes::class, classReflection: true);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $testInfo);
        Assert::same($captured->attributes, []);
    }

    public function runTestCallsNextWhenMethodHasNoInterceptableAttributes(): void
    {
        $testInfo = $this->makeTestInfo(TestWithoutAttributes::class, classReflection: false);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $testInfo);
        Assert::same($captured->attributes, []);
    }

    public function runTestCaseWithNoAttributesCallsNext(): void
    {
        $caseInfo = $this->makeCaseInfo(null);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new CaseResult([], Status::Passed);

        $captured = null;
        $result = $interceptor->runTestCase($caseInfo, function (CaseInfo $info) use (&$captured, $terminal): CaseResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $caseInfo);
        Assert::same($result, $terminal);
        Assert::same($captured->attributes, []);
    }

    public function runTestCaseCallsNextWhenClassHasNoInterceptableAttributes(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithoutAttributes::class));

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new CaseResult([], Status::Passed);

        $captured = null;
        $interceptor->runTestCase($caseInfo, function (CaseInfo $info) use (&$captured, $terminal): CaseResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $caseInfo);
        Assert::same($captured->attributes, []);
    }

    public function runTestPassesUntouchedTestInfoToNextWhenNoAttributes(): void
    {
        $testInfo = $this->makeTestInfo(TestWithoutAttributes::class, classReflection: false);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $testInfo);
    }

    public function runTestCasePassesUntouchedCaseInfoToNextWhenNoAttributes(): void
    {
        $caseInfo = $this->makeCaseInfo(null);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new CaseResult([], Status::Passed);

        $captured = null;
        $interceptor->runTestCase($caseInfo, function (CaseInfo $info) use (&$captured, $terminal): CaseResult {
            $captured = $info;
            return $terminal;
        });

        Assert::same($captured, $caseInfo);
    }

    public function runTestGroupsClassAttributeIntoTestInfoAndRunsItsInterceptor(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithClassInterceptableAttribute::class));
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithClassInterceptableAttribute::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());

        $captured = null;
        $result = $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured): TestResult {
            $captured = $info;
            return new TestResult($info, Status::Passed);
        });

        $this->assertGroupedAttribute($captured->attributes, TestClassInterceptableAttribute::class, 1);
        // The FallbackInterceptor ran for real: it tags the result the terminal produced.
        Assert::same($result->getAttribute('intercepted'), true);
    }

    public function runTestGroupsMethodAttributeIntoTestInfoAndRunsItsInterceptor(): void
    {
        $caseInfo = $this->makeCaseInfo(null);
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithMethodInterceptableAttribute::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $result = $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        $this->assertGroupedAttribute($captured->attributes, TestMethodInterceptableAttribute::class, 1);
        Assert::same($result->getAttribute('intercepted'), true);
    }

    public function runTestMergesClassThenMethodAttributesKeyedByClass(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithClassAndMethodInterceptableAttributes::class));
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithClassAndMethodInterceptableAttributes::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        // Two distinct attribute classes => two keys, each a non-empty list of one.
        $this->assertGroupedAttribute($captured->attributes, TestClassInterceptableAttribute::class, 1);
        $this->assertGroupedAttribute($captured->attributes, TestMethodInterceptableAttribute::class, 1);

        // Precedence: array_merge($classAttributes, $methodAttributes) => class key comes first.
        Assert::same(
            \array_keys($captured->attributes),
            [TestClassInterceptableAttribute::class, TestMethodInterceptableAttribute::class],
        );
    }

    public function runTestGroupsRepeatedSameClassAttributesIntoSingleList(): void
    {
        $caseInfo = $this->makeCaseInfo(null);
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithRepeatedInterceptableAttribute::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new TestResult($testInfo, Status::Passed);

        $captured = null;
        $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured, $terminal): TestResult {
            $captured = $info;
            return $terminal;
        });

        // Two attributes of the SAME class collapse into one key with a two-element list.
        Assert::same(\array_keys($captured->attributes), [TestRepeatableInterceptableAttribute::class]);
        $this->assertGroupedAttribute($captured->attributes, TestRepeatableInterceptableAttribute::class, 2);
    }

    public function runTestCaseGroupsClassAttributeIntoCaseInfo(): void
    {
        // Class attribute whose fallback targets TestRunInterceptor (not a case interceptor):
        // it is filtered out by fromAttributes(TestCaseRunInterceptor::class), so no interceptor
        // runs, yet groupAttributes() still records it on the CaseInfo — the real work under test.
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithClassInterceptableAttribute::class));

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());
        $terminal = new CaseResult([], Status::Passed);

        $captured = null;
        $result = $interceptor->runTestCase($caseInfo, function (CaseInfo $info) use (&$captured, $terminal): CaseResult {
            $captured = $info;
            return $terminal;
        });

        $this->assertGroupedAttribute($captured->attributes, TestClassInterceptableAttribute::class, 1);
        Assert::same($result, $terminal);
    }

    public function runTestCombinesIntoNextWhenNextIsPipelineInstance(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithClassInterceptableAttribute::class));
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithClassInterceptableAttribute::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']))->with(
            static fn(TestInfo $info): TestResult => new TestResult($info, Status::Passed),
            'runTest',
        );

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());

        $result = $interceptor->runTest($testInfo, $pipeline);

        // The attribute interceptor was combined into the pipeline and tagged the result.
        Assert::same($result->getAttribute('intercepted'), true);
        // The grouped attributes reached the terminal through the combined pipeline.
        $this->assertGroupedAttribute($result->info->attributes, TestClassInterceptableAttribute::class, 1);
    }

    public function runTestCaseCombinesIntoNextWhenNextIsPipelineInstance(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithCaseInterceptableAttribute::class));

        $terminal = new CaseResult([], Status::Passed);
        $pipeline = Pipeline::prepare(new PipeOptions(includeTypes: ['unit']))->with(
            static fn(CaseInfo $info): CaseResult => $terminal,
            'runTestCase',
        );

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());

        $result = $interceptor->runTestCase($caseInfo, $pipeline);

        // The case interceptor was combined into the pipeline and rewrote the status.
        Assert::same($result->status, Status::Flaky);
    }

    public function runTestCasePreparesPipelineAroundClosureNext(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithCaseInterceptableAttribute::class));

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());

        // $next is a plain CLOSURE, not a Pipeline => hits the Pipeline::prepare(...)->with(..., 'runTestCase')
        // branch. The method name MUST be 'runTestCase' (not 'runTest'), or the case interceptor would be
        // invoked with the wrong method and fail.
        $result = $interceptor->runTestCase($caseInfo, static fn(CaseInfo $info): CaseResult => new CaseResult([], Status::Passed));

        // The prepared pipeline ran the case fallback interceptor, which rewrote the status.
        Assert::same($result->status, Status::Flaky);
    }

    public function runTestPreparesPipelineAroundClosureNext(): void
    {
        $caseInfo = $this->makeCaseInfo(new \ReflectionClass(TestWithClassInterceptableAttribute::class));
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod(TestWithClassInterceptableAttribute::class, 'test'),
        );
        $testInfo = new TestInfo('test', $caseInfo, $testDefinition);

        $interceptor = new AttributesInterceptor($this->createInterceptorProvider());

        // $next is a plain CLOSURE, not a Pipeline => hits the Pipeline::prepare(...)->with(...) branch.
        $captured = null;
        $result = $interceptor->runTest($testInfo, function (TestInfo $info) use (&$captured): TestResult {
            $captured = $info;
            return new TestResult($info, Status::Passed);
        });

        // The prepared pipeline ran the fallback interceptor before reaching the closure terminal.
        Assert::same($result->getAttribute('intercepted'), true);
        $this->assertGroupedAttribute($captured->attributes, TestClassInterceptableAttribute::class, 1);
    }

    /**
     * @param array<non-empty-string, mixed> $attributes
     * @param class-string $key
     */
    private function assertGroupedAttribute(array $attributes, string $key, int $expectedCount): void
    {
        Assert::true(\array_key_exists($key, $attributes));
        Assert::array($attributes[$key]);
        Assert::count($attributes[$key], $expectedCount);
        Assert::same(\array_is_list($attributes[$key]), true);
        foreach ($attributes[$key] as $attr) {
            Assert::instanceOf($attr, $key);
        }
    }

    private function makeTestInfo(string $class, bool $classReflection): TestInfo
    {
        $caseInfo = $this->makeCaseInfo($classReflection ? new \ReflectionClass($class) : null);
        $testDefinition = new TestDefinition(
            reflection: new \ReflectionMethod($class, 'test'),
        );

        return new TestInfo('test', $caseInfo, $testDefinition);
    }

    private function makeCaseInfo(?\ReflectionClass $reflection): CaseInfo
    {
        return new CaseInfo(new CaseDefinition(
            name: 'TestCase',
            type: 'unit',
            reflection: $reflection,
        ));
    }

    private function createInterceptorProvider(): InterceptorProvider
    {
        return new InterceptorProvider(new ObjectContainer());
    }
}

final class TestWithoutAttributes
{
    public function test(): void {}
}

#[TestClassInterceptableAttribute]
final class TestWithClassInterceptableAttribute
{
    public function test(): void {}
}

final class TestWithMethodInterceptableAttribute
{
    #[TestMethodInterceptableAttribute]
    public function test(): void {}
}

#[TestCaseInterceptableAttribute]
final class TestWithCaseInterceptableAttribute
{
    public function test(): void {}
}

#[TestClassInterceptableAttribute]
final class TestWithClassAndMethodInterceptableAttributes
{
    #[TestMethodInterceptableAttribute]
    public function test(): void {}
}

final class TestWithRepeatedInterceptableAttribute
{
    #[TestRepeatableInterceptableAttribute]
    #[TestRepeatableInterceptableAttribute]
    public function test(): void {}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(TestTagRunInterceptor::class)]
final class TestClassInterceptableAttribute implements Interceptable {}

#[\Attribute(\Attribute::TARGET_METHOD)]
#[FallbackInterceptor(TestTagRunInterceptor::class)]
final class TestMethodInterceptableAttribute implements Interceptable {}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(TestTagCaseRunInterceptor::class)]
final class TestCaseInterceptableAttribute implements Interceptable {}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[FallbackInterceptor(TestTagRunInterceptor::class)]
final class TestRepeatableInterceptableAttribute implements Interceptable {}

/**
 * Distinguishable effect: tags the result so a real pass-through differs from a no-op.
 */
final class TestTagRunInterceptor implements TestRunInterceptor
{
    public function runTest(TestInfo $info, callable $next): TestResult
    {
        return $next($info)->withAttribute('intercepted', true);
    }
}

/**
 * Distinguishable effect: rewrites the result status so a real pass-through differs from a no-op.
 */
final class TestTagCaseRunInterceptor implements TestCaseRunInterceptor
{
    public function runTestCase(CaseInfo $info, callable $next): CaseResult
    {
        $result = $next($info);
        return new CaseResult($result->results, Status::Flaky, $result->summary);
    }
}
