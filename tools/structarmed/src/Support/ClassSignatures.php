<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Support;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\UnionType;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Extracts the fully-qualified type names a class exposes on its public surface:
 * the parameter and return types of its public and protected methods, and the
 * types of its public and protected properties (promoted ones included).
 *
 * StructArmed's MethodNode records only counts (paramCount, hasReturnType), not
 * type names, so a signature-level rule has to read them from the AST. Names are
 * resolved to FQCN with NameResolver so callers can match against a namespace.
 * Parsed once per file and cached.
 */
final class ClassSignatures
{
    /** @var array<string, array<string, list<string>>> file => short class name => FQ type names on its public surface */
    private static array $cache = [];

    /**
     * @return list<string>
     */
    public static function publicTypes(string $file, string $shortName): array
    {
        return (self::$cache[$file] ??= self::parse($file))[$shortName] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parse(string $file): array
    {
        $code = @\file_get_contents($file);

        if ($code === false) {
            return [];
        }

        try {
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];

            $traverser = new NodeTraverser(new NameResolver());
            $ast = $traverser->traverse($ast);
        } catch (\Throwable) {
            return [];
        }

        $signatures = [];

        foreach ((new NodeFinder())->findInstanceOf($ast, ClassLike::class) as $class) {
            $name = $class->name?->toString();

            if ($name === null) {
                continue;
            }

            $types = [];

            foreach ($class->getMethods() as $method) {
                if ($method->isPrivate()) {
                    continue;
                }

                foreach ($method->params as $param) {
                    // A privately promoted constructor param is a private property, not surface.
                    ($param->flags & Modifiers::PRIVATE) === 0 and self::collect($param->type, $types);
                }

                self::collect($method->returnType, $types);
            }

            foreach ($class->getProperties() as $property) {
                $property->isPrivate() or self::collect($property->type, $types);
            }

            $signatures[$name] = \array_values(\array_unique($types));
        }

        return $signatures;
    }

    /**
     * @param list<string> $types
     */
    private static function collect(?Node $type, array &$types): void
    {
        switch (true) {
            case $type instanceof Name:
                $types[] = $type->toString();
                break;
            case $type instanceof NullableType:
                self::collect($type->type, $types);
                break;
            case $type instanceof UnionType:
            case $type instanceof IntersectionType:
                foreach ($type->types as $inner) {
                    self::collect($inner, $types);
                }
                break;
                // A plain Identifier (int, string, void, self, static, ...) carries no namespace: ignore.
        }
    }
}
