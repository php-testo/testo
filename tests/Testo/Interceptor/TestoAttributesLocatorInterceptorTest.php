<?php

declare(strict_types=1);

namespace Tests\Testo\Interceptor;

use Testo\Application\Middleware\Locator\TestoAttributesLocatorInterceptor;
use Testo\Assert;
use Testo\Attribute\Test;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Fixture\Interceptor\TestClassWithClassLevelAttribute;
use Tests\Fixture\Interceptor\TestClassWithMethodLevelAttributes;

final class TestoAttributesLocatorInterceptorTest
{
    private string $fixturesDir = __DIR__ . '/../../Fixture/Interceptor/';
    private TestoAttributesLocatorInterceptor $interceptor;

    public function __construct()
    {
        $this->interceptor = new TestoAttributesLocatorInterceptor();
    }

    /**
     * Verifies that the interceptor correctly:
     * - Finds methods with #[Test] attribute (methodOne, methodTwo)
     * - Excludes methods without #[Test] attribute (nonTestMethodOne, nonTestMethodTwo)
     * - Excludes non-public methods with #[Test] attribute (nonTestMethodThree)
     */
    #[Test(description: 'Locates test cases from methods with #[Test] attributes')]
    public function itLocatesTestCasesFromClassWithTestAttributesOnMethods(): void
    {
        $path = $this->fixturesDir . 'TestClassWithMethodLevelAttributes.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
        );

        Assert::true($this->interceptor->locateFile($file, fn($f) => true));
        $this->interceptor->locateTestCases($definition, fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same(TestClassWithMethodLevelAttributes::class, $case->reflection->name);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('methodOne', 'methodTwo')
            ->doesNotHaveKeys('nonTestMethodOne', 'nonTestMethodTwo', 'nonTestMethodThree');
    }

    /**
     * Verifies that the interceptor correctly:
     * - Treats all public methods as tests when #[Test] is on the class
     * - Excludes protected and private methods
     */
    #[Test(description: 'Locates all public methods as tests when class has #[Test] attribute')]
    public function itLocatesAllPublicMethodsAsTestsWhenClassHasTestAttribute(): void
    {
        $path = $this->fixturesDir . 'TestClassWithClassLevelAttribute.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
        );

        Assert::true($this->interceptor->locateFile($file, fn($f) => true));
        $this->interceptor->locateTestCases($definition, fn(FileDefinitions $f) => $f->cases);

        $case = $definition->cases->getCases()[0];
        $tests = $case->tests->getTests();

        Assert::same(TestClassWithClassLevelAttribute::class, $case->reflection->name);

        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('methodOne', 'methodTwo')
            ->doesNotHaveKeys('protectedMethod', 'privateMethod');
    }

    #[Test(description: 'Verifies that classes without #[Test] attributes (neither on class nor methods) are ignored by the interceptor.')]
    public function itReturnsNoTestCasesWhenClassHasNoTestAttributes(): void
    {
        $path = $this->fixturesDir . 'PlainClassWithoutTestAttributes.php';
        $definition = new FileDefinitions(
            $file = new TokenizedFile(
                file: new \SplFileInfo($path),
                path: $path,
            ),
        );

        Assert::true($this->interceptor->locateFile($file, fn($f) => true));
        Assert::array($definition->cases->getCases())->hasCount(0);
    }
}
