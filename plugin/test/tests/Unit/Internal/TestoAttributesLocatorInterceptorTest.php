<?php

declare(strict_types=1);

namespace Tests\Test\Unit\Internal;

use Testo\Assert;
use Testo\Test;
use Testo\Test\Internal\TestoAttributesLocatorInterceptor;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Test\Unit\Fixture\TestClassWithClassLevelAttribute;
use Tests\Test\Unit\Fixture\TestClassWithMethodLevelAttributes;
use Tests\Test\Unit\Fixture\TestClassWithMixedTestAttributes;
use Tests\Test\Unit\Fixture\TestClassWithNeverReturnType;

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
     * - Finds methods with #[Test] attribute (methodOne, methodTwo)
     * - Excludes methods without #[Test] attribute (nonTestMethodOne, nonTestMethodTwo)
     * - Excludes non-public methods with #[Test] attribute (nonTestMethodThree)
     */
    #[Test]
    public function itLocatesTestCasesFromClassWithTestAttributesOnMethods(): void
    {
        $path = $this->fixturesDir . 'TestClassWithMethodLevelAttributes.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        $this->interceptor->locateTestCases($definition, static fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same($case->reflection->name, TestClassWithMethodLevelAttributes::class);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('methodOne', 'methodTwo')
            ->doesNotHaveKeys('nonTestMethodOne', 'nonTestMethodTwo', 'nonTestMethodThree');
    }

    /**
     * Locates all public methods as tests when class has #[Test] attribute
     *
     * Verifies that the interceptor correctly:
     * - Treats all public methods as tests when #[Test] is on the class
     * - Excludes protected and private methods
     */
    #[Test]
    public function itLocatesAllPublicMethodsAsTestsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'TestClassWithClassLevelAttribute.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
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
    #[Test]
    public function itLocatesNeverReturnTypeMethodsAsTestsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'TestClassWithNeverReturnType.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
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
    #[Test]
    public function itLocatesMethodWithTestAttributeRegardlessOfReturnType(): void
    {
        $path = $this->fixturesDir . 'TestClassWithMixedTestAttributes.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
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
    #[Test]
    public function itReturnsNoTestCasesWhenClassHasNoTestAttributes(): void
    {
        $path = $this->fixturesDir . 'PlainClassWithoutTestAttributes.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
        );

        Assert::true($this->interceptor->locateFile($file, static fn($f) => true));
        Assert::array($definition->cases->getCases())->hasCount(0);
    }
}
