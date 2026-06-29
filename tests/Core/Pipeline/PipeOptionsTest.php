<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\PipeOptions;
use Testo\Test;

/**
 * Unit tests for the {@see PipeOptions} value object: the test-type filter flag and the
 * interceptor-acceptance rule (universal kept, exclude takes precedence over include).
 */
#[Test]
#[Covers(PipeOptions::class)]
final class PipeOptionsTest
{
    public function defaultsHaveNoTypeFilter(): void
    {
        $options = new PipeOptions();

        Assert::false($options->hasTypeFilter());
        Assert::same($options->includeTypes, []);
        Assert::same($options->excludeTypes, []);
    }

    public function includeTypesEnableTypeFilter(): void
    {
        Assert::true((new PipeOptions(includeTypes: ['bench']))->hasTypeFilter());
    }

    public function excludeTypesEnableTypeFilter(): void
    {
        Assert::true((new PipeOptions(excludeTypes: ['bench']))->hasTypeFilter());
    }

    public function acceptsUniversalInterceptorRegardlessOfFilter(): void
    {
        Assert::true((new PipeOptions(excludeTypes: ['bench']))->acceptsTypes([]));
        Assert::true((new PipeOptions(includeTypes: ['test']))->acceptsTypes([]));
    }

    public function emptyFilterAcceptsAnyDeclaredType(): void
    {
        Assert::true((new PipeOptions())->acceptsTypes(['bench']));
    }

    public function includeKeepsOnlyMatchingDeclaredTypes(): void
    {
        $options = new PipeOptions(includeTypes: ['test']);

        Assert::true($options->acceptsTypes(['test']));
        Assert::false($options->acceptsTypes(['bench']));
    }

    public function excludeDropsDeclaredType(): void
    {
        Assert::false((new PipeOptions(excludeTypes: ['bench']))->acceptsTypes(['bench']));
    }

    public function excludeKeepsInterceptorWhenAnotherDeclaredTypeSurvives(): void
    {
        Assert::true((new PipeOptions(excludeTypes: ['bench']))->acceptsTypes(['bench', 'test']));
    }

    public function excludeTakesPrecedenceOverInclude(): void
    {
        Assert::false((new PipeOptions(includeTypes: ['bench'], excludeTypes: ['bench']))->acceptsTypes(['bench']));
    }
}
