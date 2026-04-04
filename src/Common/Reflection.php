<?php

declare(strict_types=1);

namespace Testo\Common;

/**
 * Reflection utilities.
 */
final class Reflection
{
    public const MERGE_FIRST = 1;
    public const MERGE_LAST = 2;
    public const MERGE_ALL = 3;

    /**
     * Fetch all attributes for a given function or method.
     *
     * @param \ReflectionFunctionAbstract $function The function or method to fetch attributes from.
     * @param bool $includePrototypes Whether to include attributes from method prototypes (only applicable for methods).
     * @param class-string|null $attributeClass If provided, only attributes of this class will be returned.
     * @param int $flags Flags to pass to {@see ReflectionFunctionAbstract::getAttributes()}.
     * @param int<1, max> $limit Maximum number of attributes to return. If reached, the search will stop early.
     * @param self::MERGE_* $mergePolicy Controls how attributes from prototype layers are combined.
     *        Layers without matching attributes are skipped.
     *        - {@see self::MERGE_ALL} — merge attributes from all layers (default)
     *        - {@see self::MERGE_FIRST} — take attributes from the first layer that has them
     *        - {@see self::MERGE_LAST} — take attributes from the last (most distant parent) layer that has them
     *
     * @return \ReflectionAttribute[]
     */
    public static function fetchFunctionAttributes(
        \ReflectionFunctionAbstract $function,
        bool $includePrototypes = true,
        ?string $attributeClass = null,
        int $flags = 0,
        int $limit = \PHP_INT_MAX,
        int $mergePolicy = self::MERGE_ALL,
    ): array {
        $attributes = [];
        $lastNonEmpty = [];

        do {
            $current = $function->getAttributes($attributeClass, $flags);

            if ($current !== []) {
                if ($mergePolicy === self::MERGE_FIRST && $attributes === []) {
                    return \count($current) > $limit ? \array_slice($current, 0, $limit) : $current;
                }

                if ($mergePolicy === self::MERGE_LAST) {
                    $lastNonEmpty = $current;
                } else {
                    $attributes = \array_merge($attributes, $current);

                    if (\count($attributes) >= $limit) {
                        return \array_slice($attributes, 0, $limit);
                    }
                }
            }

            if ($includePrototypes && $function instanceof \ReflectionMethod && $function->hasPrototype()) {
                $function = $function->getPrototype();
                continue;
            }

            break;
        } while (true);

        if ($mergePolicy === self::MERGE_LAST) {
            return \count($lastNonEmpty) > $limit ? \array_slice($lastNonEmpty, 0, $limit) : $lastNonEmpty;
        }

        return $attributes;
    }

    /**
     * Fetch all attributes for a given class.
     *
     * @template T
     *
     * @param \ReflectionClass|class-string $class
     * @param bool $includeParents Whether to include attributes from parent classes.
     * @param bool $includeTraits Whether to include attributes from traits.
     * @param class-string<T>|null $attributeClass If provided, only attributes of this class will be returned.
     * @param int $flags Flags to pass to {@see ReflectionClass::getAttributes()}.
     * @param int<1, max> $limit Maximum number of attributes to return. If reached, the search will stop early.
     * @param self::MERGE_* $mergePolicy Controls how attributes from parent layers are combined.
     *        Layers without matching attributes are skipped.
     *        - {@see self::MERGE_ALL} — merge attributes from all layers (default)
     *        - {@see self::MERGE_FIRST} — take attributes from the first layer that has them
     *        - {@see self::MERGE_LAST} — take attributes from the last (most distant parent) layer that has them
     *
     * @return ($attributeClass is null ? list<\ReflectionAttribute> : list<\ReflectionAttribute<T>>)
     */
    public static function fetchClassAttributes(
        \ReflectionClass|string $class,
        bool $includeParents = true,
        bool $includeTraits = true,
        ?string $attributeClass = null,
        int $flags = 0,
        int $limit = \PHP_INT_MAX,
        int $mergePolicy = self::MERGE_ALL,
    ): array {
        $attributes = [];
        $lastNonEmpty = [];

        do {
            \is_string($class) and $class = new \ReflectionClass($class);

            $current = $class->getAttributes($attributeClass, $flags);

            if ($includeTraits) {
                foreach (self::fetchTraits($class->getName(), includeParents: false) as $trait) {
                    $traitReflection = new \ReflectionClass($trait);
                    $current = \array_merge(
                        $current,
                        $traitReflection->getAttributes($attributeClass, $flags),
                    );
                }
            }

            if ($current !== []) {
                if ($mergePolicy === self::MERGE_FIRST && $attributes === []) {
                    return \count($current) > $limit ? \array_slice($current, 0, $limit) : $current;
                }

                if ($mergePolicy === self::MERGE_LAST) {
                    $lastNonEmpty = $current;
                } else {
                    $attributes = \array_merge($attributes, $current);

                    if (\count($attributes) >= $limit) {
                        return \array_slice($attributes, 0, $limit);
                    }
                }
            }

            $class = $includeParents ? $class->getParentClass() : false;
        } while ($class !== false);

        if ($mergePolicy === self::MERGE_LAST) {
            return \count($lastNonEmpty) > $limit ? \array_slice($lastNonEmpty, 0, $limit) : $lastNonEmpty;
        }

        return $attributes;
    }

    /**
     * Get every class trait (including traits used in parents).
     *
     * @param class-string $class
     * @param bool $includeParents Whether to include traits from parent classes.
     *
     * @return non-empty-string[]
     */
    public static function fetchTraits(
        string $class,
        bool $includeParents = true,
    ): array {
        $traits = [];

        do {
            $traits = \array_merge(\class_uses($class), $traits);
            $class = \get_parent_class($class);
        } while ($includeParents && $class !== false);

        //Traits from traits
        foreach (\array_flip($traits) as $trait) {
            $traits = \array_merge(\class_uses($trait), $traits);
        }

        return \array_unique($traits);
    }

    /**
     * Find all methods in a class that have a specific attribute.
     *
     * @param \ReflectionClass|class-string $class The class to inspect.
     * @param class-string $attributeClass The attribute class to search for.
     * @param bool $includePrototypes Whether to search for the attribute in method prototypes if not found on the method itself.
     * @param ReflectionAttribute::* $flags Flags to pass to {@see ReflectionMethod::getAttributes()}.
     *
     * @return \ReflectionMethod[] An array of methods that have the specified attribute.
     */
    public static function findMethodsWithAttribute(
        \ReflectionClass|string $class,
        string $attributeClass,
        bool $includePrototypes = true,
        int $flags = 0,
    ): array {
        \is_string($class) and $class = new \ReflectionClass($class);

        $methods = [];
        foreach ($class->getMethods() as $method) {
            do {
                if ($method->getAttributes($attributeClass, $flags) !== []) {
                    $methods[] = $method;
                    break;
                }

                if ($method->hasPrototype()) {
                    $method = $method->getPrototype();
                    continue;
                }

                break;
            } while ($includePrototypes);
        }

        return $methods;
    }

    /**
     * Get all attributes from the current call stack.
     *
     * Attributes are returned in the order from the deepest call (closest to the point where this method is invoked)
     * to the topmost call (root of the call stack). This follows the natural order of {@see debug_backtrace()}.
     *
     * @note Attributes may be duplicated in the result if multiple methods in the call stack belong to the same
     * class or to classes in the same inheritance hierarchy. For example, if the call stack contains both
     * ChildClass::method() and ParentClass::method() and $includeClasses is true with $includeParents set to true,
     * attributes from ParentClass will appear twice. This is intentional behavior that reflects the call stack
     * structure.
     *
     * @template T
     *
     * @param class-string<T>|null $attributeClass If provided, only attributes of this class will be returned.
     * @param bool $includePrototypes Whether to include attributes from method prototypes. Only applicable for methods
     *        in the call stack.
     * @param bool $includeClasses Whether to include attributes from the class containing the method. Only applicable
     *        for methods in the call stack.
     * @param bool $includeParents Whether to include attributes from parent classes. Only applicable when
     *        $includeClasses is true.
     * @param bool $includeTraits Whether to include attributes from traits. Only applicable when $includeClasses is
     *        true.
     * @param int<1, max> $limit Maximum number of attributes to return. If reached, the search will stop early.
     *        Defaults to PHP_INT_MAX (no practical limit).
     * @param ReflectionAttribute::* $flags Flags to pass to {@see ReflectionFunctionAbstract::getAttributes()}.
     * @return list<\ReflectionAttribute<T>>
     */
    public static function getAttributesFromCallStack(
        ?string $attributeClass,
        bool $includePrototypes = true,
        bool $includeClasses = false,
        bool $includeParents = true,
        bool $includeTraits = true,
        int $limit = \PHP_INT_MAX,
        int $flags = 0,
    ): array {
        $attributes = [];
        $backtrace = \debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($backtrace as $frame) {
            try {
                $reflection = match (true) {
                    isset($frame['class'], $frame['function']) => new \ReflectionMethod(
                        $frame['class'],
                        $frame['function'],
                    ),
                    isset($frame['function']) => new \ReflectionFunction($frame['function']),
                    default => null,
                };

                if ($reflection === null) {
                    continue;
                }

                $functionAttributes = self::fetchFunctionAttributes(
                    $reflection,
                    includePrototypes: $includePrototypes,
                    attributeClass: $attributeClass,
                    flags: $flags,
                );
                $attributes = \array_merge($attributes, $functionAttributes);

                // Include class attributes if requested and the reflection is a method
                if ($includeClasses && $reflection instanceof \ReflectionMethod) {
                    $classAttributes = self::fetchClassAttributes(
                        $reflection->getDeclaringClass(),
                        includeParents: $includeParents,
                        includeTraits: $includeTraits,
                        attributeClass: $attributeClass,
                        flags: $flags,
                    );
                    $attributes = \array_merge($attributes, $classAttributes);
                }

                // Early exit if limit is reached
                if (\count($attributes) >= $limit) {
                    return \array_slice($attributes, 0, $limit);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $attributes;
    }
}
