<?php

declare(strict_types=1);

namespace Testo\Pipeline\Internal;

use Testo\Common\Reflection;
use Testo\Pipeline\Attribute\InterceptorOptions;
use Testo\Pipeline\Interceptor as TInterceptor;
use Testo\Pipeline\PipeOptions;
use Testo\Pipeline\Policy\ConflictPolicy;

/**
 * Sorts and filters interceptors.
 *
 * @internal
 * @psalm-internal Testo\Pipeline
 */
final class Sorter
{
    /** @var array<class-string<TInterceptor>, ConflictPolicy> */
    private static array $conflictPolicyCache = [];

    /** @var array<class-string<TInterceptor>, int> */
    private static array $orderCache = [];

    /** @var array<class-string<TInterceptor>, list<non-empty-string>> */
    private static array $filterCache = [];

    /**
     * Sort and filter interceptors.
     *
     * @param TInterceptor[] $interceptors
     *
     * @return list<TInterceptor>
     */
    public static function sortAndFilter(array $interceptors, PipeOptions $options = new PipeOptions()): array
    {
        # Local caches
        $conflicts = [];
        $orders = [];
        $filters = [];

        # When no type filtering is requested every interceptor is kept; skip the per-class lookup.
        $filterByType = $options->hasTypeFilter();

        /** @var array<int, array<class-string, TInterceptor>> $groups */
        $groups = [];
        foreach ($interceptors as $interceptor) {
            $class = $interceptor::class;
            $conflict = $conflicts[$class] ??= self::getConflictPolicy($interceptor);
            $order = $orders[$class] ??= self::getOrder($interceptor);
            if ($filterByType) {
                $declared = $filters[$class] ??= self::$filterCache[$class] ?? [];
                if (!$options->acceptsTypes($declared)) {
                    continue;
                }
            }

            switch ($conflict) {
                case ConflictPolicy::First:
                    $groups[$order][$class] ??= $interceptor;
                    break;
                case ConflictPolicy::Last:
                    unset($groups[$order][$class]);
                    $groups[$order][$class] = $interceptor;
                    break;
                case ConflictPolicy::Error:
                    \array_key_exists($class, $groups[$order] ?? [])
                        ? throw new \RuntimeException("Conflict detected for interceptor '$class' with policy 'Error'.")
                        : $groups[$order][$class] = $interceptor;
                    break;
                default:
                    $groups[$order][] = $interceptor;
                    break;
            }
        }

        \ksort($groups);
        return \array_values(\array_merge(...$groups));
    }

    /**
     * Warm up the cache for the given interceptor class.
     *
     * @param class-string<TInterceptor> $class
     */
    private static function warmUpCache(string $class): void
    {
        /** @var list<\ReflectionAttribute<InterceptorOptions>> $attributes */
        $attributes = Reflection::fetchClassAttributes(
            $class,
            attributeClass: InterceptorOptions::class,
        );

        if ($attributes === []) {
            self::$conflictPolicyCache[$class] = ConflictPolicy::default();
            self::$orderCache[$class] = InterceptorOptions::ORDER_DEFAULT;
            return;
        }

        $attribute = $attributes[0]->newInstance();
        self::$conflictPolicyCache[$class] = $attribute->onConflict;
        self::$orderCache[$class] = $attribute->order;
        self::$filterCache[$class] = self::normalizeTestTypeFilter($attribute->testType);
    }

    /**
     * Get the conflict policy of the given interceptor by its attribute.
     */
    private static function getConflictPolicy(TInterceptor $interceptor): ConflictPolicy
    {
        $class = $interceptor::class;
        \array_key_exists($class, self::$conflictPolicyCache) or self::warmUpCache($class);
        return self::$conflictPolicyCache[$class];
    }

    /**
     * Get the order of the given interceptor by its attribute.
     */
    private static function getOrder(TInterceptor $interceptor): int
    {
        $class = $interceptor::class;
        \array_key_exists($class, self::$orderCache) or self::warmUpCache($class);
        return self::$orderCache[$class];
    }

    /**
     * Get the test type filter of the given interceptor by its attribute.
     *
     * @param list<non-empty-string|\BackedEnum>|non-empty-string|\BackedEnum $testType
     *
     * @return list<non-empty-string>
     */
    private static function normalizeTestTypeFilter(\BackedEnum|array|string $testType): array
    {
        if (!\is_array($testType)) {
            /** @var non-empty-string $value */
            $value = \is_string($testType) ? $testType : (string) $testType->value;
            return [$value];
        }

        $result = [];
        foreach ($testType as $type) {
            /** @var non-empty-string $value */
            $value = \is_string($type) ? $type : (string) $type->value;
            $result[] = $value;
        }

        return $result;
    }
}
