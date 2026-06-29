<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

use Testo\Spec\SpecHeader;

/**
 * Reads the first {@see SpecHeader} attribute off a class or function/method reflection.
 *
 * Shared by the interceptors (which read headers while running) and the ordering interceptors (which
 * read them at discovery to sort), so heading/number extraction lives in one place.
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final class SpecHeaderReader
{
    public static function section(?\ReflectionClass $reflection): ?SpecHeader
    {
        return $reflection === null ? null : self::first($reflection->getAttributes(SpecHeader::class));
    }

    public static function item(\ReflectionFunctionAbstract $reflection): ?SpecHeader
    {
        return self::first($reflection->getAttributes(SpecHeader::class));
    }

    /**
     * @param array<\ReflectionAttribute<SpecHeader>> $attributes
     */
    private static function first(array $attributes): ?SpecHeader
    {
        return ($attributes[0] ?? null)?->newInstance();
    }
}
