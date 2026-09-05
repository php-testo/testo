<?php

declare(strict_types=1);

namespace Tests\Lifecycle\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeClass;
use Testo\Lifecycle\BeforeTest;
use Testo\Lifecycle\Internal\LifecycleInterceptor;
use Testo\Test;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Lifecycle\Unit\Fixture\ClassWithLifecycleMethods;
use Tests\Lifecycle\Unit\Fixture\ClassWithMultipleLifecycleOnOneMethod;
use Tests\Lifecycle\Unit\Fixture\ClassWithoutLifecycle;
use Tests\Lifecycle\Unit\Fixture\PrunedFunctionsState;

#[Test]
#[Covers(LifecycleInterceptor::class)]
final class LifecycleInterceptorTest
{
    private string $fixturesDir = __DIR__ . '/../Fixture/';
    private LifecycleInterceptor $interceptor;

    public function __construct()
    {
        $this->interceptor = new LifecycleInterceptor();
    }

    /**
     * When the test plugin has registered every public void method of a class
     * (because the class carries the {@see Test} attribute), the lifecycle
     * locator must strip out methods marked with lifecycle attributes.
     */
    public function removesLifecycleMethodsFromTestSet(): void
    {
        $definition = $this->makeDefinitionWithAllPublicMethodsAsTests(
            'ClassWithLifecycleMethods.php',
            ClassWithLifecycleMethods::class,
        );

        # Sanity: pre-filter, all six public methods are present
        Assert::array($definition->cases->getCases()[0]->tests->getTests())
            ->hasCount(6)
            ->hasKeys('plainTest', 'anotherPlainTest', 'setUp', 'tearDown', 'setUpClass', 'tearDownClass');

        $result = $this->interceptor->locateTestCases(
            $definition,
            static fn(FileDefinitions $f) => $f->cases,
        );

        $tests = $result->getCases()[0]->tests->getTests();

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('plainTest', 'anotherPlainTest')
            ->doesNotHaveKeys('setUp', 'tearDown', 'setUpClass', 'tearDownClass');
    }

    /**
     * Classes without lifecycle attributes must pass through untouched.
     */
    public function passesThroughWhenNoLifecycleMethodsPresent(): void
    {
        $definition = $this->makeDefinitionWithAllPublicMethodsAsTests(
            'ClassWithoutLifecycle.php',
            ClassWithoutLifecycle::class,
        );

        $result = $this->interceptor->locateTestCases(
            $definition,
            static fn(FileDefinitions $f) => $f->cases,
        );

        Assert::array($result->getCases()[0]->tests->getTests())
            ->hasCount(2)
            ->hasKeys('alpha', 'beta');
    }

    /**
     * A method carrying several lifecycle attributes at once must still be removed exactly once.
     */
    public function removesMethodCarryingMultipleLifecycleAttributes(): void
    {
        $definition = $this->makeDefinitionWithAllPublicMethodsAsTests(
            'ClassWithMultipleLifecycleOnOneMethod.php',
            ClassWithMultipleLifecycleOnOneMethod::class,
        );

        $result = $this->interceptor->locateTestCases(
            $definition,
            static fn(FileDefinitions $f) => $f->cases,
        );

        Assert::array($result->getCases()[0]->tests->getTests())
            ->hasCount(1)
            ->hasKeys('realTest')
            ->doesNotHaveKeys('both');
    }

    /**
     * The interceptor must delegate to {@see $next} and return its result.
     */
    public function returnsValueReturnedByNext(): void
    {
        $definition = $this->makeDefinitionWithAllPublicMethodsAsTests(
            'ClassWithoutLifecycle.php',
            ClassWithoutLifecycle::class,
        );

        $nextCalled = false;
        $result = $this->interceptor->locateTestCases(
            $definition,
            static function (FileDefinitions $f) use (&$nextCalled): \Testo\Core\Definition\CaseDefinitions {
                $nextCalled = true;
                return $f->cases;
            },
        );

        Assert::true($nextCalled);
        Assert::same($result, $definition->cases);
    }

    /**
     * A function-based test case (one whose {@see \Testo\Core\Definition\CaseDefinition::$reflection}
     * is null, i.e. a file of top-level functions) must have its lifecycle-annotated functions
     * stripped from the test set exactly like the methods of a class-based case.
     */
    public function removesLifecycleFunctionsFromTestSet(): void
    {
        $definition = $this->makeDefinitionWithAllFunctionsAsTests('FunctionsWithLifecycle.php');

        # Sanity: pre-filter, all six functions are present
        Assert::array($definition->cases->getCases()[0]->tests->getTests())
            ->hasCount(6)
            ->hasKeys('fnPlainTest', 'fnAnotherPlainTest', 'fnSetUp', 'fnTearDown', 'fnSetUpClass', 'fnTearDownClass');

        $result = $this->interceptor->locateTestCases(
            $definition,
            static fn(FileDefinitions $f) => $f->cases,
        );

        $tests = $result->getCases()[0]->tests->getTests();

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('fnPlainTest', 'fnAnotherPlainTest')
            ->doesNotHaveKeys('fnSetUp', 'fnTearDown', 'fnSetUpClass', 'fnTearDownClass');
    }

    /**
     * A function-based case without lifecycle attributes must pass through untouched.
     */
    public function passesThroughWhenNoLifecycleFunctionsPresent(): void
    {
        $definition = $this->makeDefinitionWithAllFunctionsAsTests('FunctionsWithoutLifecycle.php');

        $result = $this->interceptor->locateTestCases(
            $definition,
            static fn(FileDefinitions $f) => $f->cases,
        );

        Assert::array($result->getCases()[0]->tests->getTests())
            ->hasCount(2)
            ->hasKeys('fnAlpha', 'fnBeta');
    }

    /**
     * Hooks of a function-based case come from its non-test functions, not from the surviving
     * tests: an outer case interceptor may prune every test before this interceptor runs, and the
     * `#[BeforeClass]`/`#[AfterClass]` hooks must still fire.
     */
    public function runsClassHooksForFunctionCaseWhoseTestsWereAllPruned(): void
    {
        # Functions are not autoloadable: load the fixture to reach the counters.
        require_once $this->fixturesDir . 'PrunedFunctionsWithLifecycle.php';
        PrunedFunctionsState::$beforeClassCalls = 0;
        PrunedFunctionsState::$afterClassCalls = 0;
        $info = $this->makeFunctionCaseInfoWithoutTests($this->fixturesDir . 'PrunedFunctionsWithLifecycle.php');

        $hooksAtNext = null;
        $beforeClassAtNext = null;
        $afterClassAtNext = null;
        $this->interceptor->runTestCase(
            $info,
            static function (CaseInfo $case) use (&$hooksAtNext, &$beforeClassAtNext, &$afterClassAtNext): CaseResult {
                $hooksAtNext = $case->getAttribute(LifecycleInterceptor::class, []);
                $beforeClassAtNext = PrunedFunctionsState::$beforeClassCalls;
                $afterClassAtNext = PrunedFunctionsState::$afterClassCalls;
                return new CaseResult(results: [], status: Status::Passed);
            },
        );

        # All four hooks were discovered from the case's non-tests and published for the inner pipeline.
        Assert::array($hooksAtNext)
            ->hasKeys(BeforeClass::class, AfterClass::class, BeforeTest::class, AfterTest::class);
        # The class hooks fire exactly once, around the inner pipeline: BeforeClass has already
        # fired when `$next` runs, AfterClass has not yet.
        Assert::same($beforeClassAtNext, 1);
        Assert::same($afterClassAtNext, 0);
        Assert::same(PrunedFunctionsState::$beforeClassCalls, 1);
        Assert::same(PrunedFunctionsState::$afterClassCalls, 1);
    }

    /**
     * A function-based definition with no recorded non-test functions has no discoverable hooks —
     * the case must still pass through the pipeline untouched.
     */
    public function passesThroughFunctionCaseWithoutNonTestFunctions(): void
    {
        $info = $this->makeFunctionCaseInfoWithoutTests($this->fixturesDir . 'ClassWithoutLifecycle.php');
        $expected = new CaseResult(results: [], status: Status::Passed);

        $result = $this->interceptor->runTestCase($info, static fn(): CaseResult => $expected);

        Assert::same($result, $expected);
    }

    private function makeDefinitionWithAllPublicMethodsAsTests(string $fixture, string $classFqn): FileDefinitions
    {
        $path = $this->fixturesDir . $fixture;
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions($file, classes: DefinitionLocator::getClasses($file));

        $reflection = $definition->classes[$classFqn] ?? throw new \RuntimeException(
            "Fixture class {$classFqn} not found in {$path}",
        );

        $case = $definition->cases->define($reflection, $definition, type: TestType::Test);
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $case->tests->define($method);
        }

        return $definition;
    }

    /**
     * Build a function-based {@see FileDefinitions} (null case reflection) registering every
     * top-level function of the fixture as a test — the pre-filter state the lifecycle locator
     * is expected to prune.
     */
    private function makeDefinitionWithAllFunctionsAsTests(string $fixture): FileDefinitions
    {
        $path = $this->fixturesDir . $fixture;
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $functions = DefinitionLocator::getFunctions($file);
        $definition = new FileDefinitions($file, functions: $functions);

        $case = $definition->cases->define(null, $definition, type: TestType::Test);
        foreach ($functions as $function) {
            $case->tests->define($function);
        }

        return $definition;
    }

    /**
     * Build a {@see CaseInfo} over a function-based case (null reflection) with no tests, only the
     * file's free functions as non-tests — the state an outer case interceptor leaves behind after
     * pruning every test.
     */
    private function makeFunctionCaseInfoWithoutTests(string $path): CaseInfo
    {
        $file = Path::create($path);
        $tokenized = new TokenizedFile(file: new \SplFileInfo((string) $file), path: $file);
        $fileDefinition = new FileDefinitions($tokenized, functions: DefinitionLocator::getFunctions($tokenized));
        $definition = $fileDefinition->cases->define(null, $fileDefinition, type: TestType::Test);

        return new CaseInfo(definition: $definition, suiteIdentity: new SuiteIdentity('Lifecycle/Unit'));
    }
}
