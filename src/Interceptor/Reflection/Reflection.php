<?php

declare(strict_types=1);

namespace Testo\Interceptor\Reflection;

/**
 * Reflection utilities.
 */
final class Reflection
{
    /**
     * Fetch all attributes for a given function or method.
     *
     * @param \ReflectionFunctionAbstract $function The function or method to fetch attributes from.
     * @param bool $includeParents Whether to include attributes from parent methods (only applicable for methods).
     * @param class-string|null $attributeClass If provided, only attributes of this class will be returned.
     * @param int $flags Flags to pass to {@see ReflectionFunctionAbstract::getAttributes()}.
     *
     * @return \ReflectionAttribute[]
     */
    public static function fetchFunctionAttributes(
        \ReflectionFunctionAbstract $function,
        bool $includeParents = true,
        ?string $attributeClass = null,
        int $flags = 0,
    ): array {
        $attributes = [];

        do {
            $attributes = \array_merge($attributes, $function->getAttributes($attributeClass, $flags));

            if ($includeParents && $function instanceof \ReflectionMethod) {
                $parentClass = $function->getDeclaringClass()->getParentClass();
                if ($parentClass !== false && $parentClass->hasMethod($function->getName())) {
                    $function = $parentClass->getMethod($function->getName());
                    continue;
                }
            }

            break;
        } while (true);

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
     *
     * @return ($attributeClass is null ? list<\ReflectionAttribute> : list<\ReflectionAttribute<T>>)
     */
    public static function fetchClassAttributes(
        \ReflectionClass|string $class,
        bool $includeParents = true,
        bool $includeTraits = true,
        ?string $attributeClass = null,
        int $flags = 0,
    ): array {
        $attributes = [];

        do {
            \is_string($class) and $class = new \ReflectionClass($class);

            $attributes = \array_merge(
                $attributes,
                $class->getAttributes($attributeClass, $flags),
            );

            if ($includeTraits) {
                foreach (self::fetchTraits($class->getName(), includeParents: false) as $trait) {
                    $traitReflection = new \ReflectionClass($trait);
                    $attributes = \array_merge(
                        $attributes,
                        $traitReflection->getAttributes($attributeClass, $flags),
                    );
                }
            }

            $class = $includeParents ? $class->getParentClass() : false;
        } while ($class !== false);

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
}
