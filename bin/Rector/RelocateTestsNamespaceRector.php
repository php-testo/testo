<?php

declare(strict_types=1);

namespace Testo\PhpUnitBuild\Rector;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Build-only local rule: relocate the `Tests\` namespace prefix to `Tests\PhpUnit\` across the
 * generated mirror so it autoloads alongside the original Testo suite without colliding.
 *
 * AST-based (not text surgery): every {@see Name} node — the `namespace` declaration, `use`
 * imports and qualified references alike — gets `PhpUnit` spliced in after a leading `Tests`
 * segment. String FQNs (e.g. `new ReflectionFunction('Tests\Foo\bar')`) are handled through the
 * {@see String_} node on its decoded value, so php-parser re-encodes the escaping correctly.
 *
 * Not part of the public bridge: it lives under bin/ and is wired only into
 * bin/rector-phpunit-rename.php. The `=== 'PhpUnit'` guard keeps it idempotent.
 */
final class RelocateTestsNamespaceRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Relocate the Tests\\ namespace prefix to Tests\\PhpUnit\\ in the generated PHPUnit mirror',
            [
                new CodeSample(
                    <<<'PHP'
                        namespace Tests\Assert\Feature;
                        PHP,
                    <<<'PHP'
                        namespace Tests\PhpUnit\Assert\Feature;
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Name::class, String_::class];
    }

    /**
     * @param Name|String_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        return $node instanceof String_
            ? $this->relocateString($node)
            : $this->relocateName($node);
    }

    private function relocateName(Name $name): ?Name
    {
        $parts = $name->getParts();

        if (($parts[0] ?? null) !== 'Tests' || ($parts[1] ?? null) === 'PhpUnit') {
            return null;
        }

        \array_splice($parts, 1, 0, 'PhpUnit');

        // Preserve the concrete Name subtype (Name / FullyQualified / Relative).
        $class = $name::class;

        return new $class($parts, $name->getAttributes());
    }

    private function relocateString(String_ $string): ?String_
    {
        // Decoded value: a leading (optionally rooted) `Tests\` FQN, not already relocated.
        if (\preg_match('/^\\\\?Tests\\\\(?!PhpUnit\\\\)/', $string->value) !== 1) {
            return null;
        }

        $relocated = \preg_replace('/^(\\\\?)Tests\\\\/', '$1Tests\\\\PhpUnit\\\\', $string->value);

        return new String_($relocated, $string->getAttributes());
    }
}
