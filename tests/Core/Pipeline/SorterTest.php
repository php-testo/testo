<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Interceptor;
use Testo\Pipeline\Internal\Sorter;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Policy\ConflictPolicy;
use Testo\Test;

#[Test]
#[Covers(Sorter::class)]
final class SorterTest
{
    public function sortsEmptyInterceptorList(): void
    {
        $result = Sorter::sortAndFilter([]);

        Assert::same($result, []);
    }

    public function sortsInterceptorsByOrder(): void
    {
        $low = new FirstConflictInterceptorOrder100();
        $high = new FirstConflictInterceptorOrder200();
        $middle = new FirstConflictInterceptorOrder150();

        $result = Sorter::sortAndFilter([$high, $low, $middle]);

        Assert::same(\count($result), 3);
        Assert::same($result[0], $low);
        Assert::same($result[1], $middle);
        Assert::same($result[2], $high);
    }

    public function usesFirstWhenConflictPolicyIsFirst(): void
    {
        $first = new FirstConflictInterceptorOrder100();
        $second = new FirstConflictInterceptorOrder100();

        $result = Sorter::sortAndFilter([$first, $second]);

        Assert::same(\count($result), 1);
        Assert::same($result[0], $first);
    }

    public function usesLastWhenConflictPolicyIsLast(): void
    {
        $first = new LastConflictInterceptor();
        $second = new LastConflictInterceptor();

        $result = Sorter::sortAndFilter([$first, $second]);

        Assert::same(\count($result), 1);
        Assert::same($result[0], $second);
    }

    public function throwsWhenConflictPolicyIsError(): void
    {
        $first = new ErrorConflictInterceptor();
        $second = new ErrorConflictInterceptor();

        try {
            Sorter::sortAndFilter([$first, $second]);
            Assert::fail('Should have thrown RuntimeException for conflict');
        } catch (\RuntimeException $e) {
            Assert::true(\str_contains($e->getMessage(), 'Conflict detected'));
            Assert::true(\str_contains($e->getMessage(), 'Error'));
        }
    }

    public function keepsSingleInterceptorWhenConflictPolicyIsError(): void
    {
        $interceptor = new ErrorConflictInterceptor();

        $result = Sorter::sortAndFilter([$interceptor]);

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function mergesInterceptorsWhenConflictPolicyIsMerge(): void
    {
        $first = new MergeConflictInterceptor();
        $second = new MergeConflictInterceptor();

        $result = Sorter::sortAndFilter([$first, $second]);

        Assert::same(\count($result), 2);
        Assert::same($result[0], $first);
        Assert::same($result[1], $second);
    }

    public function filtersInterceptorsByTypeString(): void
    {
        $included = new FilterTestInterceptorUnit();
        $excluded = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter([$included, $excluded], options: new PipeOptions(includeTypes: ['unit']));

        Assert::same(\count($result), 1);
        Assert::same($result[0], $included);
    }

    public function includesInterceptorWhenNoTypeFilterIsSpecified(): void
    {
        $interceptor = new FilterTestInterceptorUnit();

        $result = Sorter::sortAndFilter([$interceptor]);

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function excludesInterceptorWhenTypeDoesNotMatch(): void
    {
        $interceptor = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['unit']));

        Assert::same(\count($result), 0);
    }

    public function filtersMultipleTypesByArray(): void
    {
        $interceptor = new FilterTestInterceptorMultiType();

        $result1 = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['unit']));
        $result2 = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['feature']));
        $result3 = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['integration']));

        Assert::same(\count($result1), 1);
        Assert::same(\count($result2), 1);
        Assert::same(\count($result3), 0);
    }

    public function handlesMultipleOrdersWithinSameGroup(): void
    {
        $order100 = new FirstConflictInterceptorOrder100();
        $order100Two = new FirstConflictInterceptorOrder100();
        $order200 = new FirstConflictInterceptorOrder200();

        $result = Sorter::sortAndFilter([$order200, $order100Two, $order100]);

        Assert::same(\count($result), 2);
        Assert::true($result[0] instanceof FirstConflictInterceptorOrder100);
        Assert::true($result[1] instanceof FirstConflictInterceptorOrder200);
    }

    public function normalizesBackedEnumTestType(): void
    {
        $interceptor = new FilterTestInterceptorBackedEnum();

        $matched = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['unit']));
        $excluded = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['feature']));

        Assert::same(\count($matched), 1);
        Assert::same($matched[0], $interceptor);
        Assert::same($excluded, []);
    }

    public function normalizesArrayOfBackedEnums(): void
    {
        $interceptor = new FilterTestInterceptorArrayOfEnums();

        $unit = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['unit']));
        $feature = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['feature']));
        $integration = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['integration']));

        Assert::same(\count($unit), 1);
        Assert::same($unit[0], $interceptor);
        Assert::same(\count($feature), 1);
        Assert::same($feature[0], $interceptor);
        Assert::same($integration, []);
    }

    public function handlesInterceptorWithoutAttribute(): void
    {
        $interceptor = new NoAttributeTestInterceptor();

        $result = Sorter::sortAndFilter([$interceptor]);

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function maintainsMultipleConflictPoliciesInSingleSort(): void
    {
        $first = new FirstConflictInterceptorOrder100();
        $firstTwo = new FirstConflictInterceptorOrder100();
        $merge = new MergeConflictInterceptorOrder200();
        $mergeTwo = new MergeConflictInterceptorOrder200();

        $result = Sorter::sortAndFilter([$firstTwo, $first, $mergeTwo, $merge]);

        Assert::same(\count($result), 3);
        Assert::same($result[0], $firstTwo);
        Assert::same($result[1], $mergeTwo);
        Assert::same($result[2], $merge);
    }

    public function emptyFilterBypassesTestTypeFilter(): void
    {
        $interceptor = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter([$interceptor], options: new PipeOptions());

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function excludeDropsInterceptorOfExcludedType(): void
    {
        $unit = new FilterTestInterceptorUnit();
        $feature = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter([$unit, $feature], options: new PipeOptions(excludeTypes: ['feature']));

        Assert::same(\count($result), 1);
        Assert::same($result[0], $unit);
    }

    public function excludeKeepsUniversalInterceptor(): void
    {
        $universal = new NoAttributeTestInterceptor();
        $feature = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter([$universal, $feature], options: new PipeOptions(excludeTypes: ['feature']));

        Assert::same(\count($result), 1);
        Assert::same($result[0], $universal);
    }

    public function excludeKeepsMultiTypeInterceptorWhenAnotherTypeSurvives(): void
    {
        # Declares ['unit', 'feature']; excluding only 'feature' leaves 'unit' surviving.
        $interceptor = new FilterTestInterceptorMultiType();

        $result = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(excludeTypes: ['feature']));

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function excludeTakesPrecedenceOverInclude(): void
    {
        $feature = new FilterTestInterceptorFeature();

        $result = Sorter::sortAndFilter(
            [$feature],
            options: new PipeOptions(includeTypes: ['feature'], excludeTypes: ['feature']),
        );

        Assert::same($result, []);
    }

    public function sortsLargeSetOfInterceptors(): void
    {
        $interceptors = [
            new FirstConflictInterceptorOrder200(),
            new FirstConflictInterceptorOrder100(),
            new FirstConflictInterceptorOrder150(),
        ];

        $result = Sorter::sortAndFilter($interceptors);

        Assert::same(\count($result), 3);
        Assert::true($result[0] instanceof FirstConflictInterceptorOrder100);
        Assert::true($result[1] instanceof FirstConflictInterceptorOrder150);
        Assert::true($result[2] instanceof FirstConflictInterceptorOrder200);
    }

    public function includesEmptyFilterArray(): void
    {
        $interceptor = new FilterTestInterceptorEmpty();

        $result = Sorter::sortAndFilter([$interceptor], options: new PipeOptions(includeTypes: ['unit']));

        Assert::same(\count($result), 1);
        Assert::same($result[0], $interceptor);
    }

    public function ordersAcrossNegativeZeroAndPositiveOrders(): void
    {
        $negative = new NegativeOrderInterceptor();
        $positive = new FirstConflictInterceptorOrder100();
        $defaultOrder = new NoAttributeTestInterceptor();

        $result = Sorter::sortAndFilter([$positive, $defaultOrder, $negative]);

        Assert::same(\count($result), 3);
        Assert::same($result[0], $negative);
        Assert::same($result[1], $defaultOrder);
        Assert::same($result[2], $positive);
    }

    public function differentClassesAtSameOrderAreNotAConflict(): void
    {
        $first = new ErrorConflictInterceptor();
        $second = new SecondErrorConflictInterceptor();

        $result = Sorter::sortAndFilter([$first, $second]);

        Assert::same(\count($result), 2);
        Assert::same($result[0], $first);
        Assert::same($result[1], $second);
    }
}

#[InterceptorOptions(order: 100)]
final class FirstConflictInterceptorOrder100 implements Interceptor {}

#[InterceptorOptions(order: 150)]
final class FirstConflictInterceptorOrder150 implements Interceptor {}

#[InterceptorOptions(order: 200)]
final class FirstConflictInterceptorOrder200 implements Interceptor {}

#[InterceptorOptions(order: 100, onConflict: ConflictPolicy::Last)]
final class LastConflictInterceptor implements Interceptor {}

#[InterceptorOptions(order: 100, onConflict: ConflictPolicy::Error)]
final class ErrorConflictInterceptor implements Interceptor {}

#[InterceptorOptions(order: 100, onConflict: ConflictPolicy::Error)]
final class SecondErrorConflictInterceptor implements Interceptor {}

#[InterceptorOptions(order: -100)]
final class NegativeOrderInterceptor implements Interceptor {}

#[InterceptorOptions(order: 100, onConflict: ConflictPolicy::Merge)]
final class MergeConflictInterceptor implements Interceptor {}

#[InterceptorOptions(order: 200, onConflict: ConflictPolicy::Merge)]
final class MergeConflictInterceptorOrder200 implements Interceptor {}

#[InterceptorOptions(testType: 'unit')]
final class FilterTestInterceptorUnit implements Interceptor {}

#[InterceptorOptions(testType: 'feature')]
final class FilterTestInterceptorFeature implements Interceptor {}

#[InterceptorOptions(testType: ['unit', 'feature'])]
final class FilterTestInterceptorMultiType implements Interceptor {}

#[InterceptorOptions(testType: TestTypeEnum::Unit)]
final class FilterTestInterceptorBackedEnum implements Interceptor {}

#[InterceptorOptions(testType: [TestTypeEnum::Unit, TestTypeEnum::Feature])]
final class FilterTestInterceptorArrayOfEnums implements Interceptor {}

final class NoAttributeTestInterceptor implements Interceptor {}

#[InterceptorOptions(testType: [])]
final class FilterTestInterceptorEmpty implements Interceptor {}

enum TestTypeEnum: string
{
    case Unit = 'unit';
    case Feature = 'feature';
}
