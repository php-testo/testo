<?php

declare(strict_types=1);

namespace Tests\Filter\Unit;

use Internal\Path;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Filter;
use Testo\Test;

/**
 * Unit tests for the immutable {@see Filter} DTO: default state, path normalization in the
 * constructor, and the copy-on-write semantics of {@see Filter::with()}.
 */
#[Test]
#[Covers(Filter::class)]
final class FilterTest
{
    public function defaultsAreEmpty(): void
    {
        $filter = new Filter();

        Assert::same($filter->suites, []);
        Assert::same($filter->names, []);
        Assert::same($filter->paths, []);
        Assert::same($filter->type, []);
        Assert::same($filter->notType, []);
        Assert::same($filter->groups, []);
        Assert::same($filter->excludeGroups, []);
    }

    public function constructorStoresScalarAndListFields(): void
    {
        $filter = new Filter(
            suites: ['Unit', 'Integration'],
            names: ['UserTest::testLogin'],
            type: ['test'],
            notType: ['bench'],
            groups: ['db'],
            excludeGroups: ['slow'],
        );

        Assert::same($filter->suites, ['Unit', 'Integration']);
        Assert::same($filter->names, ['UserTest::testLogin']);
        Assert::same($filter->type, ['test']);
        Assert::same($filter->notType, ['bench']);
        Assert::same($filter->groups, ['db']);
        Assert::same($filter->excludeGroups, ['slow']);
    }

    public function constructorNormalizesPathsToAbsolutePathObjects(): void
    {
        $filter = new Filter(paths: [__DIR__, __FILE__]);

        Assert::array($filter->paths)->hasCount(2)->allOf(Path::class);
        Assert::same((string) $filter->paths[0], (string) Path::create(__DIR__)->absolute());
        Assert::same((string) $filter->paths[1], (string) Path::create(__FILE__)->absolute());
    }

    public function withReturnsANewInstance(): void
    {
        $filter = new Filter(names: ['a']);
        $copy = $filter->with(names: ['b']);

        Assert::notSame($copy, $filter);
        Assert::same($filter->names, ['a']);
        Assert::same($copy->names, ['b']);
    }

    public function withNullKeepsEachExistingField(): void
    {
        $filter = new Filter(
            suites: ['Unit'],
            names: ['x'],
            type: ['bench'],
            notType: ['inline'],
            groups: ['db'],
            excludeGroups: ['slow'],
        );

        $copy = $filter->with();

        Assert::same($copy->suites, ['Unit']);
        Assert::same($copy->names, ['x']);
        Assert::same($copy->type, ['bench']);
        Assert::same($copy->notType, ['inline']);
        Assert::same($copy->groups, ['db']);
        Assert::same($copy->excludeGroups, ['slow']);
    }

    public function withOverridesEachProvidedField(): void
    {
        $copy = (new Filter())->with(
            testSuites: ['S'],
            names: ['N'],
            groups: ['G'],
            excludeGroups: ['E'],
        );

        Assert::same($copy->suites, ['S']);
        Assert::same($copy->names, ['N']);
        Assert::same($copy->groups, ['G']);
        Assert::same($copy->excludeGroups, ['E']);
    }

    public function withNullTypeKeepsExistingType(): void
    {
        Assert::same((new Filter(type: ['test']))->with(type: null)->type, ['test']);
    }

    public function withEmptyArrayTypeResetsType(): void
    {
        Assert::same((new Filter(type: ['test']))->with(type: [])->type, []);
    }

    public function withNonEmptyTypeReplacesType(): void
    {
        Assert::same((new Filter(type: ['test']))->with(type: ['bench'])->type, ['bench']);
    }

    public function withNullNotTypeKeepsExistingNotType(): void
    {
        Assert::same((new Filter(notType: ['bench']))->with(notType: null)->notType, ['bench']);
    }

    public function withReplacesNotType(): void
    {
        Assert::same((new Filter(notType: ['bench']))->with(notType: ['inline'])->notType, ['inline']);
    }

    public function withReplacesPaths(): void
    {
        $copy = (new Filter(paths: [__DIR__]))->with(paths: [__FILE__]);

        Assert::array($copy->paths)->hasCount(1);
        Assert::same((string) $copy->paths[0], (string) Path::create(__FILE__)->absolute());
    }
}
