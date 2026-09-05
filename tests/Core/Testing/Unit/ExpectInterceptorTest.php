<?php

declare(strict_types=1);

namespace Tests\Core\Testing\Unit;

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
use Testo\Core\Value\Summary;
use Testo\Test;
use Testo\Testing\Attribute\ExpectAssertionsCount;
use Testo\Testing\Attribute\ExpectTestResultAttribute;
use Testo\Testing\Attribute\ExpectTestStatus;
use Testo\Testing\Internal\ExpectInterceptor;

#[Test]
#[Covers(ExpectInterceptor::class)]
#[Covers(ExpectTestStatus::class)]
#[Covers(ExpectAssertionsCount::class)]
#[Covers(ExpectTestResultAttribute::class)]
final class ExpectInterceptorTest
{
    public function passesThroughWhenNoExpectAttributesPresent(): void
    {
        $info = self::createTestInfoFor('noAttributes');
        $inner = new TestResult(info: $info, status: Status::Passed);
        $next = static fn(TestInfo $i): TestResult => $inner;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same($result, $inner);
    }

    public function passesWhenStubStatusMatchesExpected(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFailed');
        $stub = new TestResult(info: $info, status: Status::Failed);
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Passed, $result->status);
    }

    public function failsWhenStubStatusDoesNotMatchExpected(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFailed');
        $stub = new TestResult(info: $info, status: Status::Passed);
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
        Assert::instanceOf($result->failure, \RuntimeException::class);
    }

    public function passesWhenAssertionCountMatches(): void
    {
        $info = self::createTestInfoFor('fixtureExpectThreeAssertions');
        $stub = new TestResult(
            info: $info,
            status: Status::Passed,
            summary: new Summary(metrics: ['assertions' => 3]),
        );
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Passed, $result->status);
    }

    public function failsWhenAssertionCountDoesNotMatch(): void
    {
        $info = self::createTestInfoFor('fixtureExpectThreeAssertions');
        $stub = new TestResult(
            info: $info,
            status: Status::Passed,
            summary: new Summary(metrics: ['assertions' => 2]),
        );
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
    }

    public function passesWhenExpectedResultAttributeIsPresent(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFooAttribute');
        $stub = (new TestResult(info: $info, status: Status::Passed))
            ->withAttribute('foo', 'bar');
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Passed, $result->status);
    }

    public function failsWhenExpectedResultAttributeIsAbsent(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFooAttribute');
        $stub = new TestResult(info: $info, status: Status::Passed);
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
    }

    public function repeatableAttributeChecksAllKeys(): void
    {
        $info = self::createTestInfoFor('fixtureExpectTwoAttributes');
        // Only 'alpha' present; 'beta' is missing → should fail
        $stub = (new TestResult(info: $info, status: Status::Passed))
            ->withAttribute('alpha', 1);
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
    }

    public function preservesOuterFailureWithoutRunningValidation(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFailed');
        $outer = new TestResult(info: $info, status: Status::Failed);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same($result, $outer);
    }

    public function failsWithLogicExceptionWhenResultIsNotATestResult(): void
    {
        $info = self::createTestInfoFor('fixtureExpectFailed');
        $outer = new TestResult(info: $info, status: Status::Passed, result: 'not-a-test-result');
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
        Assert::instanceOf($result->failure, \LogicException::class);
    }

    public function combinesMultipleViolationsIntoSingleFailure(): void
    {
        $info = self::createTestInfoFor('fixtureExpectPassedWithThreeAssertions');
        // Stub is Failed with 1 assertion — both status and count fail
        $stub = new TestResult(
            info: $info,
            status: Status::Failed,
            summary: new Summary(metrics: ['assertions' => 1]),
        );
        $outer = new TestResult(info: $info, status: Status::Passed, result: $stub);
        $next = static fn(TestInfo $i): TestResult => $outer;

        $result = (new ExpectInterceptor())->runTest($info, $next);

        Assert::same(Status::Failed, $result->status);
    }

    // ── Attribute fixtures ────────────────────────────────────────────────────

    private function noAttributes(): void {}

    #[ExpectTestStatus(Status::Failed)]
    private function fixtureExpectFailed(): void {}

    #[ExpectAssertionsCount(3)]
    private function fixtureExpectThreeAssertions(): void {}

    #[ExpectTestResultAttribute('foo')]
    private function fixtureExpectFooAttribute(): void {}

    #[ExpectTestResultAttribute('alpha')]
    #[ExpectTestResultAttribute('beta')]
    private function fixtureExpectTwoAttributes(): void {}

    #[ExpectTestStatus(Status::Passed)]
    #[ExpectAssertionsCount(3)]
    private function fixtureExpectPassedWithThreeAssertions(): void {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function createTestInfoFor(string $method): TestInfo
    {
        $reflection = new \ReflectionMethod(self::class, $method);
        $caseDefinition = new CaseDefinition(name: 'ExpectInterceptorTest', type: 'test', file: Path::create(__FILE__));
        $caseInfo = new CaseInfo(definition: $caseDefinition, suiteIdentity: new SuiteIdentity('Core/Testing/Unit'));
        $testDefinition = new TestDefinition(reflection: $reflection);

        return new TestInfo(
            name: $method,
            caseInfo: $caseInfo,
            testDefinition: $testDefinition,
        );
    }
}
