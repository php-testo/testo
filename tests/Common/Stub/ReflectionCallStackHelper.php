<?php

declare(strict_types=1);

namespace Tests\Common\Stub;

use Testo\Common\Reflection;

/**
 * Helper class for getAttributesFromCallStack tests.
 */
#[ReflectionClassAttribute('helperClass')]
final class ReflectionCallStackHelper
{
    #[ReflectionClassAttribute('markedMethod')]
    public function markedMethod(
        ?string $attributeClass = null,
        bool $includePrototypes = true,
        bool $includeClasses = false,
        bool $includeParents = true,
        bool $includeTraits = true,
        int $limit = \PHP_INT_MAX,
        int $flags = 0,
    ): array {
        return Reflection::getAttributesFromCallStack(
            $attributeClass,
            $includePrototypes,
            $includeClasses,
            $includeParents,
            $includeTraits,
            $limit,
            $flags,
        );
    }

    public function unmarkedMethod(
        ?string $attributeClass = null,
        bool $includePrototypes = true,
        bool $includeClasses = false,
        bool $includeParents = true,
        bool $includeTraits = true,
        int $limit = \PHP_INT_MAX,
        int $flags = 0,
    ): array {
        return Reflection::getAttributesFromCallStack(
            $attributeClass,
            $includePrototypes,
            $includeClasses,
            $includeParents,
            $includeTraits,
            $limit,
            $flags,
        );
    }

    #[ReflectionClassAttribute('callerMethod')]
    public function callerMethod(
        ?string $attributeClass = null,
        bool $includePrototypes = true,
        bool $includeClasses = false,
        bool $includeParents = true,
        bool $includeTraits = true,
        int $limit = \PHP_INT_MAX,
        int $flags = 0,
    ): array {
        return $this->markedMethod(
            $attributeClass,
            $includePrototypes,
            $includeClasses,
            $includeParents,
            $includeTraits,
            $limit,
            $flags,
        );
    }
}
