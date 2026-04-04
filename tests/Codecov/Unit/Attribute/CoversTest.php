<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Attribute;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\CoversNothing;
use Testo\Codecov\Internal\CoverageAttribute;
use Testo\Test;

#[Test]
final class CoversTest
{
    public function constructWithClass(): void
    {
        $covers = new Covers(\stdClass::class);

        Assert::same($covers->classOrFunction, \stdClass::class);
        Assert::null($covers->method);
    }

    public function constructWithClassAndMethod(): void
    {
        $covers = new Covers(\stdClass::class, 'toString');

        Assert::same($covers->classOrFunction, \stdClass::class);
        Assert::same($covers->method, 'toString');
    }

    public function constructWithFunction(): void
    {
        $covers = new Covers('App\Helpers\format_name');

        Assert::same($covers->classOrFunction, 'App\Helpers\format_name');
        Assert::null($covers->method);
    }

    public function implementsCoverageAttribute(): void
    {
        Assert::instanceOf(new Covers(\stdClass::class), CoverageAttribute::class);
    }

    public function coversNothingImplementsCoverageAttribute(): void
    {
        Assert::instanceOf(new CoversNothing(), CoverageAttribute::class);
    }

    public function isRepeatable(): void
    {
        $reflection = new \ReflectionClass(Covers::class);
        $attr = $reflection->getAttributes(\Attribute::class)[0]->newInstance();

        Assert::true(($attr->flags & \Attribute::IS_REPEATABLE) !== 0);
    }

    public function readFromMethodReflection(): void
    {
        $reflection = new \ReflectionMethod(self::class, 'stubMethodWithCovers');

        $attributes = $reflection->getAttributes(CoverageAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

        Assert::count($attributes, 2);
        Assert::instanceOf($attributes[0]->newInstance(), Covers::class);
        Assert::instanceOf($attributes[1]->newInstance(), Covers::class);
    }

    public function readMixedAttributesViaInterface(): void
    {
        $reflection = new \ReflectionMethod(self::class, 'stubMethodWithMixed');

        $attributes = $reflection->getAttributes(CoverageAttribute::class, \ReflectionAttribute::IS_INSTANCEOF);

        Assert::count($attributes, 1);
        Assert::instanceOf($attributes[0]->newInstance(), CoversNothing::class);
    }

    // --- Stubs ---

    #[Covers(\stdClass::class)]
    #[Covers(\ArrayObject::class, 'count')]
    private function stubMethodWithCovers(): void {}

    #[CoversNothing]
    private function stubMethodWithMixed(): void {}
}
