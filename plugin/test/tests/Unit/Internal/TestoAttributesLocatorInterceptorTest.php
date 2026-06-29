<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Middleware\CaseLocatorInterceptor;
use Testo\Pipeline\Middleware\FileLocatorInterceptor;
use Testo\Test;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Test\Unit\Fixture\AbstractTestClassWithVoidMethods;
use Tests\Test\Unit\Fixture\TestClassWithClassLevelAttribute;
use Tests\Test\Unit\Fixture\TestClassWithMethodLevelAttributes;
use Tests\Test\Unit\Fixture\TestClassWithMixedTestAttributes;
use Tests\Test\Unit\Fixture\TestClassWithNeverReturnType;

#[Test]
#[Covers(TestoAttributesLocatorInterceptor::class)]
#[Covers(FileLocatorInterceptor::class)]
#[Covers(CaseLocatorInterceptor::class)]
final class TestoAttributesLocatorInterceptorTest
{
    private string $fixturesDir = __DIR__ . '/../Fixture/';
    private TestoAttributesLocatorInterceptor $interceptor;

    public function __construct()
    {
        $this->interceptor = new TestoAttributesLocatorInterceptor();
    }

    /**
     * Locates test cases from methods with #[Test] attributes
     *
     * Verifies that the interceptor correctly:
     * - Finds public methods with #[Test] attribute (publicTest, anotherPublicTest)
     * - Finds non-public methods with #[Test] attribute (protectedTest, privateTest)
     * - Excludes methods without #[Test] attribute (publicWithoutAttribute)
     */
    public function itLocatesTestCasesFromClassWithTestAttributesOnMethods(): void
    {
        $path = $this->fixturesDir . 'TestClassWithMethodLevelAttributes.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same($case->reflection->name, TestClassWithMethodLevelAttributes::class);

        Assert::array($tests)
            ->hasCount(4)
            ->hasKeys('publicTest', 'anotherPublicTest', 'protectedTest', 'privateTest')
            ->doesNotHaveKeys('publicWithoutAttribute');
    }

    /**
     * Locates all public methods as tests when class has #[Test] attribute
     *
     * Verifies that the interceptor correctly:
     * - Treats all public methods as tests when #[Test] is on the class
     * - Excludes protected and private methods
     */
    public function itLocatesAllPublicMethodsAsTestsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'TestClassWithClassLevelAttribute.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same($case->reflection->name, TestClassWithClassLevelAttribute::class);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('methodOne', 'methodTwo')
            ->doesNotHaveKeys('protectedMethod', 'privateMethod');
    }

    /**
     * Locates methods with never return type as tests when class has #[Test] attribute
     *
     * Verifies that the interceptor correctly:
     * - Includes public methods with void return type
     * - Includes public methods with never return type
     * - Excludes public methods with other return types (e.g. string)
     */
    public function itLocatesNeverReturnTypeMethodsAsTestsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'TestClassWithNeverReturnType.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same($case->reflection->name, TestClassWithNeverReturnType::class);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('voidMethod', 'neverMethod')
            ->doesNotHaveKeys('stringMethod');
    }

    /**
     * Locates method-level #[Test] with non-void return type in a class-level #[Test] class
     *
     * Verifies that the interceptor correctly:
     * - Includes public void methods from class-level #[Test]
     * - Includes public methods with non-void return type if they have method-level #[Test]
     * - Excludes public methods without void/never return type and without #[Test]
     */
    public function itLocatesMethodWithTestAttributeRegardlessOfReturnType(): void
    {
        $path = $this->fixturesDir . 'TestClassWithMixedTestAttributes.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same($case->reflection->name, TestClassWithMixedTestAttributes::class);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('voidMethod', 'intMethod')
            ->doesNotHaveKeys('stringMethod');
    }

    /**
     * Verifies that classes without #[Test] attributes (neither on class nor methods) are ignored by the interceptor.
     */
    public function itReturnsNoTestCasesWhenClassHasNoTestAttributes(): void
    {
        $path = $this->fixturesDir . 'PlainClassWithoutTestAttributes.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        Assert::array($definition->cases->getCases())->hasCount(0);
    }

    /**
     * Abstract methods are never picked up as tests when #[Test] is on the class.
     *
     * An abstract class cannot be instantiated, so even its concrete void methods are not
     * runnable as tests — and abstract `void` / `never` methods, although they pass the
     * "skip data providers" return-type filter, must never be discovered either.
     * The interceptor enforces this by skipping abstract classes wholesale.
     */
    public function itDoesNotLocateAbstractMethodsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'AbstractTestClassWithVoidMethods.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        # Sanity check — make sure the fixture really does contain abstract void/never methods.
        # If PHP/reflection ever changes such that this is no longer reachable, the test
        # below becomes vacuously true; this guard surfaces that.
        $reflection = new \ReflectionClass(AbstractTestClassWithVoidMethods::class);
        Assert::true($reflection->isAbstract());
        Assert::true($reflection->getMethod('abstractVoidMethod')->isAbstract());
        Assert::true($reflection->getMethod('abstractNeverMethod')->isAbstract());

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        Assert::array($definition->cases->getCases())->hasCount(0);
    }

    /**
     * Locates free functions with #[Test] as a single function-scoped test case.
     *
     * Verifies that the interceptor correctly:
     * - Defines exactly one case (with no class reflection) for all #[Test] functions in the file
     * - Adds every function carrying #[Test] as a test of that case
     * - Excludes functions without the #[Test] attribute
     */
    public function itLocatesFreeFunctionsWithTestAttributeAsSingleCase(): void
    {
        $path = $this->fixturesDir . 'TestFunctionsWithTestAttribute.php';
        $file = new TokenizedFile(file: new \SplFileInfo($path), path: $path);
        $definition = new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $cases = $definition->cases->getCases();
        Assert::array($cases)->hasCount(1);

        $case = $cases[0];
        Assert::null($case->reflection);

        Assert::array($case->tests->getTests())
            ->hasCount(2)
            ->hasKeys('functionWithTestAttribute', 'anotherFunctionWithTestAttribute')
            ->doesNotHaveKeys('functionWithoutTestAttribute');
    }

    /**
     * The locator must declare its test type so the `--type` filter can select (`--type=test`) and
     * exclude (`--type=!test`) plain tests. Without it the locator would be universal and leak past
     * the type filter, since type filtering selects finders by their declared {@see TestType}.
     */
    public function declaresTestTypeForTypeFiltering(): void
    {
        $attributes = (new \ReflectionClass(TestoAttributesLocatorInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::array($attributes)->hasCount(1);
        Assert::same($attributes[0]->newInstance()->testType, TestType::Test);
    }
}
