<?php

declare(strict_types=1);

namespace Testo\Core\Definition;

use Testo\Inline\TestInline;

/**
 * @api
 */
final readonly class TestDefinition
{
    public function __construct(
        public \ReflectionFunctionAbstract $reflection,
    ) {}

    public function getDescription(): ?string
    {
        $attributes = $this->reflection->getDocComment();
        return $attributes === false ? null : self::clearPhpDoc($attributes);
    }

    /**
     * Cut the PHPDoc comment to get the description.
     *
     * The description ends at the first block annotation (a line starting with `@`).
     */
    #[TestInline(["/**\n * Foo bar\n */"], 'Foo bar')]
    #[TestInline(["/**\n *\n * Foo bar\n *\n */"], 'Foo bar')]
    #[TestInline(["/**\n *\n Foo bar\n *\n */"], 'Foo bar')]
    #[TestInline(["/** Foo bar */"], 'Foo bar')]
    #[TestInline(["/** Foo bar\n */"], 'Foo bar')]
    #[TestInline(["/**\n * Foo * bar\n */"], 'Foo * bar')]
    #[TestInline(["/**\n * Foo\n * bar\n */"], "Foo\nbar")]
    #[TestInline(["/**\n\t* Foo\n\t*\n\t* - bar\n */"], "Foo\n\n- bar")]
    #[TestInline(["/**\n * Foo bar\n * @api\n */"], 'Foo bar')]
    #[TestInline(["/**\n * Foo bar\n *\n * @param string \$x\n * @return void\n */"], 'Foo bar')]
    #[TestInline(["/**\n * Foo\n * bar\n *\n * @api\n */"], "Foo\nbar")]
    #[TestInline(["/**\n * @api\n */"], '')]
    #[TestInline(["/** @api */"], '')]
    #[TestInline(["/**\n  @api\n*/"], '')]
    #[TestInline(["/**\n  Foo bar\n  @api\n*/"], 'Foo bar')]
    #[TestInline(["/**\n * Email like foo@bar.com\n */"], 'Email like foo@bar.com')]
    private static function clearPhpDoc(string $doc): string
    {
        $doc = \preg_replace('#^\s*/\*\*|\*/\s*$#', '', $doc) ?? $doc;
        $doc = \preg_replace('#^\s*+\*[ \x0B\t\f\r]?#m', '', $doc) ?? $doc;
        $doc = \preg_replace('#^[ \t]*@.*#ms', '', $doc) ?? $doc;

        return \trim($doc);
    }
}
