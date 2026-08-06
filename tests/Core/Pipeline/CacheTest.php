<?php

declare(strict_types=1);

namespace Tests\Core\Pipeline;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Internal\Cache;
use Testo\Pipeline\Interceptor;
use Testo\Test;

#[Test]
#[Covers(Cache::class)]
final class CacheTest
{
    /**
     * When resolveAliases is called with a class that has a FallbackInterceptor attribute,
     * it should cache and return the interceptor classes.
     */
    public function resolveAliasesWithFallbackInterceptorAttribute(): void
    {
        $result = Cache::resolveAliases(AttributeWithFallback::class);

        Assert::same($result, [MockInterceptor::class]);
    }

    /**
     * A repeated FallbackInterceptor attribute wires every listed interceptor, in declaration order.
     */
    public function resolveAliasesCollectsRepeatedFallbacks(): void
    {
        $result = Cache::resolveAliases(AttributeWithSeveralFallbacks::class);

        Assert::same($result, [MockInterceptor::class, SecondMockInterceptor::class]);
    }

    /**
     * When resolveAliases is called with a class that has no FallbackInterceptor attribute,
     * it should return an empty list.
     */
    public function resolveAliasesWithoutFallbackInterceptorAttribute(): void
    {
        $result = Cache::resolveAliases(AttributeWithoutFallback::class);

        Assert::same($result, []);
    }

    /**
     * The first resolveAliases call memoises the resolved value in the private static map,
     * so the key must be present (with the resolved value) afterwards.
     */
    public function resolveAliasesMemoisesResultInMap(): void
    {
        $map = self::mapProperty();
        $orig = $map->getValue();

        try {
            $map->setValue(null, []);

            $result = Cache::resolveAliases(AttributeWithFallbackForCache::class);
            Assert::same($result, [MockInterceptor::class]);

            $stored = $map->getValue();
            Assert::true(\array_key_exists(AttributeWithFallbackForCache::class, $stored));
            Assert::same($stored[AttributeWithFallbackForCache::class], [MockInterceptor::class]);
        } finally {
            $map->setValue(null, $orig);
        }
    }

    /**
     * Once a parent class is memoised in the map, resolving a child class walks up the
     * cached map (the do/while loop) and returns the parent's stored value.
     */
    public function resolveAliasesWalksCachedParentInMap(): void
    {
        $map = self::mapProperty();
        $orig = $map->getValue();

        try {
            $map->setValue(null, []);

            $parent = Cache::resolveAliases(ParentAttributeForMapWalk::class);
            Assert::same($parent, [MockInterceptor::class]);

            $stored = $map->getValue();
            Assert::true(\array_key_exists(ParentAttributeForMapWalk::class, $stored));
            Assert::false(\array_key_exists(ChildAttributeForMapWalk::class, $stored));

            $child = Cache::resolveAliases(ChildAttributeForMapWalk::class);
            Assert::same($child, [MockInterceptor::class]);
        } finally {
            $map->setValue(null, $orig);
        }
    }

    /**
     * A class without a FallbackInterceptor caches an empty list; the lookup uses
     * array_key_exists, so the stored empty list is a cache hit on subsequent calls.
     */
    public function resolveAliasesCachesEmptyAsHit(): void
    {
        $map = self::mapProperty();
        $orig = $map->getValue();

        try {
            $map->setValue(null, []);

            $first = Cache::resolveAliases(NoFallbackForNullCache::class);
            Assert::same($first, []);

            $stored = $map->getValue();
            Assert::true(\array_key_exists(NoFallbackForNullCache::class, $stored));
            Assert::same($stored[NoFallbackForNullCache::class], []);

            $second = Cache::resolveAliases(NoFallbackForNullCache::class);
            Assert::same($second, []);
        } finally {
            $map->setValue(null, $orig);
        }
    }

    /**
     * When resolveAliases is called with a class that inherits from a class with FallbackInterceptor,
     * it should walk up the parent class chain (reflection fallback) and find the interceptor.
     */
    public function resolveAliasesWalksParentClassHierarchy(): void
    {
        $result = Cache::resolveAliases(ChildAttributeOfFallback::class);

        Assert::same($result, [MockInterceptor::class]);
    }

    private static function mapProperty(): \ReflectionProperty
    {
        $property = (new \ReflectionClass(Cache::class))->getProperty('map');
        $property->setAccessible(true);

        return $property;
    }
}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(MockInterceptor::class)]
class AttributeWithFallback implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(MockInterceptor::class)]
#[FallbackInterceptor(SecondMockInterceptor::class)]
final class AttributeWithSeveralFallbacks implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AttributeWithoutFallback implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(MockInterceptor::class)]
final class AttributeWithFallbackForCache implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
#[FallbackInterceptor(MockInterceptor::class)]
class ParentAttributeForMapWalk implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ChildAttributeForMapWalk extends ParentAttributeForMapWalk
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoFallbackForNullCache implements Interceptable
{
}

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ChildAttributeOfFallback extends AttributeWithFallback
{
}

final class MockInterceptor implements Interceptor
{
}

final class SecondMockInterceptor implements Interceptor
{
}
