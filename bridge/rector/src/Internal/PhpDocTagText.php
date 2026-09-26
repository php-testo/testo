<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;

/**
 * Reads the text after a plain docblock tag such as `@dataProvider cases` or `@group slow`.
 *
 * Rector parses a tag whose name matches an imported class as a Doctrine annotation: with
 * `use PHPUnit\Framework\Attributes\DataProvider;` in the file, `@dataProvider cases` arrives as a
 * {@see DoctrineAnnotationTagValueNode} rather than a {@see GenericTagValueNode}. Both carry the
 * same text.
 *
 * @internal
 */
final class PhpDocTagText
{
    /**
     * @return string|null The trimmed text, or null for a tag of another kind.
     */
    public static function of(PhpDocTagNode $tag): ?string
    {
        return match (true) {
            $tag->value instanceof GenericTagValueNode => \trim($tag->value->value),
            $tag->value instanceof DoctrineAnnotationTagValueNode => \trim((string) $tag->value->getOriginalContent()),
            default => null,
        };
    }
}
