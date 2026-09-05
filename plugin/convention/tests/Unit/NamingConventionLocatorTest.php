<?php

declare(strict_types=1);

namespace Tests\Convention\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Convention\Internal\NamingConventionLocator;
use Testo\Test;
use Testo\Tokenizer\DefinitionLocator;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;

#[Test]
#[Covers(NamingConventionLocator::class)]
final class NamingConventionLocatorTest
{
    private string $fixturesDir = __DIR__ . '/Fixture/';

    public function locateFileMatchesBySuffix(): void
    {
        $locator = new NamingConventionLocator();
        $file = self::tokenize($this->fixturesDir . 'UserServiceTest.php');

        Assert::true($locator->locateFile($file, static fn() => false));
    }

    /**
     * A non-matching stem must delegate to the next locator and return its result.
     */
    public function locateFileFallsThroughWhenStemDoesNotEndWithSuffix(): void
    {
        $locator = new NamingConventionLocator();
        $file = self::tokenize($this->fixturesDir . 'NotMatching.php');
        $called = false;
        $passthrough = static function () use (&$called) {
            $called = true;
            return null;
        };

        Assert::null($locator->locateFile($file, $passthrough));
        Assert::true($called);
    }

    public function locateFileMatchesCustomSuffix(): void
    {
        $locator = new NamingConventionLocator(caseSuffix: 'Spec');
        $matching = self::tokenize($this->fixturesDir . 'EmptySpec.php');
        $notMatching = self::tokenize($this->fixturesDir . 'UserServiceTest.php');

        Assert::true($locator->locateFile($matching, static fn() => false));
        Assert::false($locator->locateFile($notMatching, static fn() => false));
    }

    public function locateTestCasesDiscoversPublicTestMethods(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'UserServiceTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $cases = $definitions->cases->getCases();
        Assert::array($cases)->hasCount(1);

        $tests = $cases[0]->tests->getTests();
        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('testCreatesUser', 'testDeletesUser')
            ->doesNotHaveKeys('testify', 'helper', 'testProtected', 'testPrivate');
    }

    public function locateTestCasesIncludesNonPublicWhenAllowPrivate(): void
    {
        $locator = new NamingConventionLocator(allowPrivate: true);
        $definitions = self::definitions($this->fixturesDir . 'UserServiceTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $tests = $definitions->cases->getCases()[0]->tests->getTests();
        Assert::array($tests)
            ->hasCount(4)
            ->hasKeys('testCreatesUser', 'testDeletesUser', 'testProtected', 'testPrivate')
            ->doesNotHaveKeys('testify', 'helper');
    }

    public function locateTestCasesSkipsAbstractClass(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'AbstractCaseTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        Assert::array($definitions->cases->getCases())->hasCount(0);
    }

    public function locateTestCasesSkipsClassWithoutSuffix(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'NotMatching.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        Assert::array($definitions->cases->getCases())->hasCount(0);
    }

    public function locateTestCasesUsesCustomPrefix(): void
    {
        $locator = new NamingConventionLocator(caseSuffix: 'Spec', testPrefix: 'it');
        $definitions = self::definitions($this->fixturesDir . 'EmptySpec.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $tests = $definitions->cases->getCases()[0]->tests->getTests();
        Assert::array($tests)
            ->hasCount(2)
            ->hasKeys('itDoesSomething', 'itHandlesEdgeCase')
            ->doesNotHaveKeys('helper');
    }

    public function locateTestCasesEmptyPrefixMatchesEveryPublicMethod(): void
    {
        $locator = new NamingConventionLocator(testPrefix: '');
        $definitions = self::definitions($this->fixturesDir . 'UserServiceTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $tests = $definitions->cases->getCases()[0]->tests->getTests();
        Assert::array($tests)
            ->hasCount(4)
            ->hasKeys('testCreatesUser', 'testDeletesUser', 'testify', 'helper');
    }

    public function locateTestCasesDiscoversStandaloneFunctions(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'FunctionsTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $cases = $definitions->cases->getCases();
        Assert::array($cases)->hasCount(1);

        $tests = $cases[0]->tests->getTests();
        Assert::array($tests)->hasCount(2);
    }

    /**
     * A file of functions none of which matches the convention still yields a case, with the
     * functions as its non-tests and no tests. Dropping such a case is not the locator's job.
     */
    public function locateTestCasesNoTestsWhenNoFunctionMatches(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'NoFunctionMatchesTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $cases = $definitions->cases->getCases();
        Assert::array($cases)->hasCount(1);
        Assert::array($cases[0]->tests->getTests())->hasCount(0);
        Assert::array($cases[0]->tests->filter(isTest: false))->hasKeys('helperOne', 'helperTwo');
    }

    public function locateTestCasesHandlesClassAndFunctionsInSameFile(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'MixedTest.php');

        $locator->locateTestCases($definitions, static fn(FileDefinitions $f) => $f->cases);

        $cases = $definitions->cases->getCases();
        Assert::array($cases)->hasCount(2);
    }

    /**
     * The next interceptor is always invoked, even for a class-only file with no functions.
     */
    public function locateTestCasesCallsNextEvenWhenNoFunctions(): void
    {
        $locator = new NamingConventionLocator();
        $definitions = self::definitions($this->fixturesDir . 'UserServiceTest.php');

        $called = false;
        $next = static function (FileDefinitions $f) use (&$called) {
            $called = true;
            return $f->cases;
        };

        $locator->locateTestCases($definitions, $next);

        Assert::true($called);
    }

    private static function tokenize(string $path): TokenizedFile
    {
        return new TokenizedFile(new \SplFileInfo($path), $path);
    }

    private static function definitions(string $path): FileDefinitions
    {
        $file = self::tokenize($path);

        return new FileDefinitions(
            $file,
            classes: DefinitionLocator::getClasses($file),
            functions: DefinitionLocator::getFunctions($file),
        );
    }
}
