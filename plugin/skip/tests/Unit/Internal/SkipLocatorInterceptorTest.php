<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Skip\Internal\SkipLocatorInterceptor;
use Testo\Test;
use Testo\Tokenizer\Reflection\FileDefinitions;
use Testo\Tokenizer\Reflection\TokenizedFile;
use Tests\Skip\Unit\Fixture\SkipClassLevelFixture;
use Tests\Skip\Unit\Fixture\SkipMixedMethodsFixture;

/**
 * @see SkipLocatorInterceptor
 */
#[Test]
#[Covers(SkipLocatorInterceptor::class)]
final class SkipLocatorInterceptorTest
{
    public function flagsMethodLevelSkipsOnly(): void
    {
        $case = self::createCase(SkipMixedMethodsFixture::class, 'skipped', 'skippedNoReason', 'enabled');

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::array($case->tests->getTests(skipped: true))->hasKeys('skipped', 'skippedNoReason');
        Assert::array($case->tests->getTests(skipped: false))->hasKeys('enabled');
    }

    public function classLevelSkipFlagsEveryTest(): void
    {
        $case = self::createCase(SkipClassLevelFixture::class, 'first', 'second', 'third');

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::array($case->tests->getTests(skipped: false))->hasCount(0);
    }

    /**
     * A test stays a member of its case and stays active: the flag is the only change, so the
     * case is neither dropped by the suite factory nor loses the test from its results.
     */
    public function flaggedTestStaysActive(): void
    {
        $case = self::createCase(SkipMixedMethodsFixture::class, 'skipped', 'enabled');

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::array($case->tests->getTests())->hasKeys('skipped', 'enabled');
    }

    /**
     * `#[Skip]` on a non-test member is inert: the interceptor walks the tests only.
     */
    public function skipOnANonTestMemberIsInert(): void
    {
        $case = self::createCaseWith(SkipMixedMethodsFixture::class, [
            'skipped' => new TestDefinition(
                new \ReflectionMethod(SkipMixedMethodsFixture::class, 'skipped'),
                isTest: false,
            ),
        ]);

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::false($case->tests->all()['skipped']->skipped);
    }

    /**
     * The interceptor does not read `active`: a test an earlier filter deactivated is flagged all
     * the same, and being inactive it is neither run nor reported either way.
     */
    public function flagsADeactivatedTestToo(): void
    {
        $case = self::createCaseWith(SkipMixedMethodsFixture::class, [
            'skipped' => new TestDefinition(
                new \ReflectionMethod(SkipMixedMethodsFixture::class, 'skipped'),
                active: false,
            ),
        ]);

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::true($case->tests->all()['skipped']->skipped);
    }

    /**
     * A flag set by someone else is not cleared by a test without `#[Skip]`.
     */
    public function keepsAFlagSetElsewhere(): void
    {
        $case = self::createCaseWith(SkipMixedMethodsFixture::class, [
            'enabled' => new TestDefinition(
                new \ReflectionMethod(SkipMixedMethodsFixture::class, 'enabled'),
                skipped: true,
            ),
        ]);

        (new SkipLocatorInterceptor())->locateTestCases(self::file(), self::next($case));

        Assert::true($case->tests->all()['enabled']->skipped);
    }

    /**
     * `#[Skip]` is a plain-test feature: a bench or inline case of the same file is left alone.
     */
    public function leavesCasesOfOtherTypesAlone(): void
    {
        $case = self::createCase(SkipClassLevelFixture::class, 'first');
        $bench = new CaseDefinition(
            name: SkipClassLevelFixture::class,
            type: TestType::BenchInline->value,
            file: Path::create(__FILE__),
            reflection: new \ReflectionClass(SkipClassLevelFixture::class),
            tests: TestDefinitions::fromArray(
                first: new TestDefinition(new \ReflectionMethod(SkipClassLevelFixture::class, 'first')),
            ),
        );

        (new SkipLocatorInterceptor())->locateTestCases(
            self::file(),
            static fn(FileDefinitions $file): CaseDefinitions => CaseDefinitions::fromArray($case, $bench),
        );

        Assert::true($case->tests->all()['first']->skipped);
        Assert::false($bench->tests->all()['first']->skipped);
    }

    public function declaresTestTypeScopingSkipToPlainTests(): void
    {
        $attributes = (new \ReflectionClass(SkipLocatorInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::count($attributes, 1);
        Assert::same($attributes[0]->newInstance()->testType, TestType::Test);
    }

    /**
     * @param class-string $class
     * @param non-empty-string ...$methods
     */
    private static function createCase(string $class, string ...$methods): CaseDefinition
    {
        $definitions = [];
        foreach ($methods as $method) {
            $definitions[$method] = new TestDefinition(new \ReflectionMethod($class, $method));
        }

        return self::createCaseWith($class, $definitions);
    }

    /**
     * @param class-string $class
     * @param array<non-empty-string, TestDefinition> $definitions
     */
    private static function createCaseWith(string $class, array $definitions): CaseDefinition
    {
        return new CaseDefinition(
            name: $class,
            type: TestType::Test->value,
            file: Path::create(__FILE__),
            reflection: new \ReflectionClass($class),
            tests: TestDefinitions::fromArray(...$definitions),
        );
    }

    private static function file(): FileDefinitions
    {
        return new FileDefinitions(new TokenizedFile(file: new \SplFileInfo(__FILE__), path: __FILE__));
    }

    private static function next(CaseDefinition $case): \Closure
    {
        return static fn(FileDefinitions $file): CaseDefinitions => CaseDefinitions::fromArray($case);
    }
}
