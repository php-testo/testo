<?php

declare(strict_types=1);

namespace Testo\Common;

/**
 * Reflection utilities.
 */
final class Reflection
{
    /**
     * Fetch all attributes for a given function or method.
     *
     * @param \ReflectionFunctionAbstract $function The function or method to fetch attributes from.
     * @param bool $includePrototypes Whether to include attributes from method prototypes (only applicable for methods).
     * @param class-string|null $attributeClass If provided, only attributes of this class will be returned.
     * @param int $flags Flags to pass to {@see ReflectionFunctionAbstract::getAttributes()}.
     *
     * @return \ReflectionAttribute[]
     */
    public static function fetchFunctionAttributes(
        \ReflectionFunctionAbstract $function,
        bool $includePrototypes = true,
        ?string $attributeClass = null,
        int $flags = 0,
    ): array {
        $attributes = [];

        do {
            $attributes = \array_merge($attributes, $function->getAttributes($attributeClass, $flags));

            if ($includePrototypes && $function instanceof \ReflectionMethod) {
                # todo use ->hasPrototype() since php 8.2
                try {
                    $function = $function->getPrototype();
                    continue;
                } catch (\ReflectionException) {
                    break;
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

                # todo use ->hasPrototype() since php 8.2
                try {
                    $method = $method->getPrototype();
                } catch (\ReflectionException) {
                    break;
                }
            } while ($includePrototypes);
        }

        return $methods;
    }
}
