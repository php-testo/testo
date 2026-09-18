<?php

declare(strict_types=1);

namespace Tests\Skip\Unit\Internal;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\Identity\SuiteIdentity;
use Testo\Core\Context\TestInfo;
use Testo\Core\Context\TestResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Exception\SkipTest;
use Testo\Core\Value\Status;
use Testo\Core\Value\TestType;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Test;
use Testo\Skip\Internal\SkipInterceptor;
use Tests\Skip\Unit\Fixture\SkipClassLevelFixture;
use Tests\Skip\Unit\Fixture\SkipMixedMethodsFixture;
use Tests\Skip\Unit\Fixture\SkipOwnReasonOverrideFixture;

/**
 * @see SkipInterceptor
 */
#[Test]
#[Covers(SkipInterceptor::class)]
final class SkipInterceptorTest
{
    /**
     * The interceptor short-circuits: `$next` (and with it every inner interceptor and the body)
     * is never reached.
     */
    public function returnsSkippedResultWithoutCallingNext(): void
    {
        $nextCalled = false;

        $result = (new SkipInterceptor())->runTest(
            self::createTestInfo(SkipMixedMethodsFixture::class, 'skipped'),
            static function (TestInfo $info) use (&$nextCalled): TestResult {
                $nextCalled = true;
                return new TestResult(info: $info, status: Status::Passed);
            },
        );

        Assert::false($nextCalled);
        Assert::same($result->status, Status::Skipped);
        Assert::instanceOf($result->failure, SkipTest::class);
        Assert::same($result->summary->count(Status::Skipped), 1);
    }

    public function composesReasonAfterGeneratedPart(): void
    {
        $result = self::skip(SkipMixedMethodsFixture::class, 'skipped');

        Assert::same(
            $result->failure?->getMessage(),
            SkipMixedMethodsFixture::class
            . '::skipped is skipped via #[Skip] ==> broken by the pricing rework, see ISSUE-123',
        );
    }

    /**
     * An empty reason falls back to the generated part alone — no reporter ever shows an
     * empty skip message.
     */
    public function fallsBackToGeneratedMessageWithoutReason(): void
    {
        $result = self::skip(SkipMixedMethodsFixture::class, 'skippedNoReason');

        Assert::same(
            $result->failure?->getMessage(),
            SkipMixedMethodsFixture::class . '::skippedNoReason is skipped via #[Skip]',
        );
    }

    /**
     * A test without an attribute of its own takes the class-level reason.
     */
    public function classLevelReasonAppliesToATestWithoutItsOwn(): void
    {
        $result = self::skip(SkipClassLevelFixture::class, 'first');

        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> entire case is skipped'));
    }

    /**
     * The method-level attribute wins as a whole, so an empty method reason is not filled in from
     * the class reason.
     */
    public function methodLevelReasonWinsOverTheClassLevelOne(): void
    {
        $own = self::skip(SkipClassLevelFixture::class, 'second');
        $empty = self::skip(SkipClassLevelFixture::class, 'third');

        Assert::true(\str_ends_with((string) $own->failure?->getMessage(), ' ==> method beats class'));
        Assert::same(
            $empty->failure?->getMessage(),
            SkipClassLevelFixture::class . '::third is skipped via #[Skip]',
        );
    }

    /**
     * The nearest declaration wins: an override repeating `#[Skip]` reports its own reason, not
     * the one on the prototype it also inherits.
     */
    public function ownReasonOfAnOverrideWinsOverTheInheritedOne(): void
    {
        $result = self::skip(SkipOwnReasonOverrideFixture::class, 'skipped');

        Assert::true(\str_ends_with((string) $result->failure?->getMessage(), ' ==> own reason of the override'));
    }

    /**
     * The terminal renders a test's PHPDoc description from the result attributes (as the
     * regular test path stamps it), so the skipped result must carry it too.
     */
    public function carriesDescriptionInResult(): void
    {
        $result = self::skip(SkipMixedMethodsFixture::class, 'skipped');

        Assert::same($result->attributes['description'], 'Checks that order totals include the reworked pricing.');
        Assert::same($result->attributes['duration'], 0);
    }

    /**
     * `#[Skip]` is a plain-test feature: without this declaration the attribute would skip a case
     * of any other type too.
     */
    public function declaresTestTypeScopingSkipToPlainTests(): void
    {
        $attributes = (new \ReflectionClass(SkipInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::count($attributes, 1);
        Assert::same($attributes[0]->newInstance()->testType, TestType::Test);
    }

    /**
     * The rest of the placement contract: the slot sits outer to everything that prepares a test
     * body, and one of the instances the attribute spawns per occurrence is enough.
     */
    public function declaresOrderAndConflictPolicy(): void
    {
        $attributes = (new \ReflectionClass(SkipInterceptor::class))
            ->getAttributes(InterceptorOptions::class);

        Assert::count($attributes, 1);
        $options = $attributes[0]->newInstance();
        Assert::true($options->order < InterceptorOptions::ORDER_DATA_PROVIDER - 1);
        Assert::true($options->order > InterceptorOptions::ORDER_FILTER);
        Assert::same($options->onConflict, ConflictPolicy::First);
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private static function skip(string $class, string $method): TestResult
    {
        return (new SkipInterceptor())->runTest(
            self::createTestInfo($class, $method),
            static fn(TestInfo $info): TestResult => throw new \LogicException('Must never be reached.'),
        );
    }

    /**
     * @param class-string $class
     * @param non-empty-string $method
     */
    private static function createTestInfo(string $class, string $method): TestInfo
    {
        $definition = new TestDefinition(new \ReflectionMethod($class, $method));
        $caseDefinition = new CaseDefinition(
            name: $class,
            type: TestType::Test->value,
            file: Path::create(__FILE__),
            reflection: new \ReflectionClass($class),
            tests: TestDefinitions::fromArray(...[$method => $definition]),
        );
        $caseInfo = new CaseInfo(definition: $caseDefinition, suiteIdentity: new SuiteIdentity('Test/Unit'));

        return new TestInfo(name: $method, caseInfo: $caseInfo, testDefinition: $definition);
    }
}
