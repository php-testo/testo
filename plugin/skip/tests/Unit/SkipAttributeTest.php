<?php

declare(strict_types=1);

namespace Tests\Skip\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Test;
use Testo\Skip\Internal\SkipInterceptor;
use Testo\Skip;

/**
 * @see Skip
 */
#[Test]
#[Covers(Skip::class)]
final class SkipAttributeTest
{
    public function defaultReasonIsEmpty(): void
    {
        $skip = new Skip();

        Assert::same($skip->reason, '');
    }

    public function customReason(): void
    {
        $skip = new Skip('flaky on CI, see ISSUE-123');

        Assert::same($skip->reason, 'flaky on CI, see ISSUE-123');
    }

    /**
     * Exactly class, method and function — and nothing else, so no `IS_REPEATABLE`.
     */
    public function targetsClassMethodAndFunctionOnly(): void
    {
        $attributes = (new \ReflectionClass(Skip::class))->getAttributes(\Attribute::class);

        Assert::count($attributes, 1);
        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();

        Assert::same(
            $attribute->flags,
            \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_FUNCTION,
        );
    }

    /**
     * The pipeline collects `Interceptable` attributes; without the marker a class-level
     * `#[Skip]` would be invisible to the attributes interceptor.
     */
    public function isInterceptable(): void
    {
        Assert::instanceOf(new Skip(), Interceptable::class);
    }

    /**
     * An `Interceptable` attribute must resolve to an interceptor, or the attributes
     * interceptor throws at pipeline build time; the fallback names the handler.
     */
    public function declaresSkipInterceptorAsFallback(): void
    {
        $attributes = (new \ReflectionClass(Skip::class))->getAttributes(FallbackInterceptor::class);

        Assert::count($attributes, 1);
        Assert::same($attributes[0]->newInstance()->class, SkipInterceptor::class);
    }
}
