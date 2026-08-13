<?php

declare(strict_types=1);

namespace Testo\Pipeline\Internal;

use Testo\Common\Reflection;
use Testo\Pipeline\Attribute\FallbackInterceptor;
use Testo\Pipeline\Attribute\Interceptable;
use Testo\Pipeline\Interceptor;

/**
 * Cached map of interceptable attributes to their interceptors.
 *
 * @internal
 * @psalm-internal Testo\Pipeline
 */
final class Cache
{
    /**
     * @var array<class-string<Interceptable>, list<class-string<Interceptor>>>
     */
    private static array $map = [];

    /**
     * Resolve alias interceptors for the given attribute class.
     *
     * @param class-string<Interceptable> $class The attribute class.
     * @return list<class-string<Interceptor>> The interceptor classes; empty if none found.
     */
    public static function resolveAliases(string $class): array
    {
        $c = $class;
        do {
            if (\array_key_exists($c, self::$map)) {
                return self::$map[$c];
            }

            $c = \get_parent_class($c);
        } while ($c);

        /**
         * Resolve fallback handlers from the repeatable {@see FallbackInterceptor} attribute
         * @var list<\ReflectionAttribute<FallbackInterceptor>> $attrs
         */
        $attrs = Reflection::fetchClassAttributes($class, attributeClass: FallbackInterceptor::class);

        return self::$map[$class] ??= \array_map(
            static fn(\ReflectionAttribute $attr): string => $attr->newInstance()->class,
            $attrs,
        );
    }
}
