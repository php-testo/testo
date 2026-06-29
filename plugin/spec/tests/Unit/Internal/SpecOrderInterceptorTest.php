<?php

declare(strict_types=1);

namespace Tests\Spec\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Core\Context\CaseInfo;
use Testo\Core\Context\CaseResult;
use Testo\Core\Context\SuiteInfo;
use Testo\Core\Context\SuiteResult;
use Testo\Core\Definition\CaseDefinition;
use Testo\Core\Definition\CaseDefinitions;
use Testo\Core\Definition\TestDefinition;
use Testo\Core\Definition\TestDefinitions;
use Testo\Core\Value\Status;
use Testo\Spec\Internal\SpecCaseOrderInterceptor;
use Testo\Spec\Internal\SpecSuiteOrderInterceptor;
use Testo\Test;
use Tests\Spec\Unit\Fixture\NumberedSectionHigh;
use Tests\Spec\Unit\Fixture\NumberedSectionLow;
use Tests\Spec\Unit\Fixture\OrderingCase;
use Tests\Spec\Unit\Fixture\UnnumberedSection;

#[Test]
#[Covers(SpecSuiteOrderInterceptor::class)]
#[Covers(SpecCaseOrderInterceptor::class)]
final class SpecOrderInterceptorTest
{
    public function sortsCasesBySectionNumberWithUnnumberedLast(): void
    {
        $cases = CaseDefinitions::fromArray(
            self::case(NumberedSectionHigh::class),
            self::case(UnnumberedSection::class),
            self::case(NumberedSectionLow::class),
        );
        $info = new SuiteInfo(name: 'S', testCases: $cases);

        (new SpecSuiteOrderInterceptor())->runTestSuite($info, static fn(): SuiteResult => new SuiteResult([], Status::Passed));

        $names = \array_map(static fn(CaseDefinition $c): ?string => $c->name, $cases->getCases());
        Assert::same($names, ['NumberedSectionLow', 'NumberedSectionHigh', 'UnnumberedSection']);
    }

    public function sortsTestsWithinACaseBySpecNumber(): void
    {
        $class = new \ReflectionClass(OrderingCase::class);
        $tests = TestDefinitions::fromArray(
            third: new TestDefinition($class->getMethod('third')),
            first: new TestDefinition($class->getMethod('first')),
            second: new TestDefinition($class->getMethod('second')),
        );
        $definition = new CaseDefinition(name: 'OrderingCase', type: 'test', reflection: $class, tests: $tests);
        $info = new CaseInfo(definition: $definition);

        (new SpecCaseOrderInterceptor())->runTestCase($info, static fn(): CaseResult => new CaseResult([], Status::Passed));

        Assert::same(\array_keys($definition->tests->getTests()), ['first', 'second', 'third']);
    }

    public function callsTheNextHandler(): void
    {
        $info = new SuiteInfo(name: 'S', testCases: CaseDefinitions::fromArray(self::case(UnnumberedSection::class)));
        $called = false;
        $next = static function () use (&$called): SuiteResult {
            $called = true;
            return new SuiteResult([], Status::Passed);
        };

        (new SpecSuiteOrderInterceptor())->runTestSuite($info, $next);

        Assert::true($called);
    }

    /**
     * @param class-string $class
     */
    private static function case(string $class): CaseDefinition
    {
        return new CaseDefinition(
            name: (new \ReflectionClass($class))->getShortName(),
            type: 'test',
            reflection: new \ReflectionClass($class),
        );
    }
}
