<?php

declare(strict_types=1);

namespace Testo\Tools\StructArmed\Support;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Throwable;

/**
 * Reads class-level PHPDoc tags (@api, @internal) straight from the source, since
 * StructArmed's ClassNode does not expose docblocks. Parsed once per file and cached,
 * so several rules querying the same file share the work.
 */
final class ClassAnnotations
{
    /** @var array<string, array<string, string>> file => short class name => docblock text */
    private static array $cache = [];

    public static function hasApi(string $file, string $shortName): bool
    {
        return self::hasTag(self::docblock($file, $shortName), 'api');
    }

    public static function hasInternal(string $file, string $shortName): bool
    {
        return self::hasTag(self::docblock($file, $shortName), 'internal');
    }

    private static function hasTag(string $docblock, string $tag): bool
    {
        return \preg_match('/@' . $tag . '\b/', $docblock) === 1;
    }

    private static function docblock(string $file, string $shortName): string
    {
        return (self::$cache[$file] ??= self::parse($file))[$shortName] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private static function parse(string $file): array
    {
        $code = @\file_get_contents($file);

        if ($code === false) {
            return [];
        }

        try {
            $ast = (new ParserFactory())->createForNewestSupportedVersion()->parse($code) ?? [];
        } catch (Throwable) {
            return [];
        }

        $docblocks = [];

        foreach ((new NodeFinder())->findInstanceOf($ast, ClassLike::class) as $node) {
            $name = $node->name?->toString();

            $name === null or $docblocks[$name] = $node->getDocComment()?->getText() ?? '';
        }

        return $docblocks;
    }
}
