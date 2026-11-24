<?php

declare(strict_types=1);

namespace Tests\Skip\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Requires;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Test;

#[Test]
#[Covers(Requires::class)]
final class RequiresAttributeTest
{
    public function reasonIsEmptyByDefault(): void
    {
        Assert::same((new Requires('gc_enabled'))->reason, '');
    }

    public function keepsClosureCondition(): void
    {
        $condition = static fn(): bool => true;

        Assert::same((new Requires($condition))->condition, $condition);
    }

    public function repeatableOnClassMethodAndFunction(): void
    {
        $attributes = (new \ReflectionClass(Requires::class))->getAttributes(\Attribute::class);

        Assert::count($attributes, 1);
        Assert::same(
            $attributes[0]->newInstance()->flags,
            \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION
            | \Attribute::IS_REPEATABLE,
        );
    }

    public function sharesTheSkipInterceptor(): void
    {
        $attributes = (new \ReflectionClass(Requires::class))->getAttributes(FallbackInterceptor::class);

        Assert::instanceOf(new Requires('gc_enabled'), Interceptable::class);
        Assert::count($attributes, 1);
        Assert::same($attributes[0]->newInstance()->class, SkipInterceptor::class);
    }
}
