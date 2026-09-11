<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites Testo's `#[Testo\Codecov\Covers(X::class)]` class attribute into the PHPUnit attribute that
 * matches the KIND of `X`:
 *   - a class or enum => `#[\PHPUnit\Framework\Attributes\CoversClass(X::class)]`
 *   - a trait         => `#[\PHPUnit\Framework\Attributes\CoversTrait(X::class)]`
 *   - an interface    => the attribute is DROPPED
 *
 * PHPUnit only accepts a concrete code unit as a coverage target: `#[CoversClass]` on an interface or a
 * trait is "not a valid target for code coverage" (an interface has no executable code; a trait is a
 * separate target kind). The kind is resolved by autoload — `interface_exists()`/`trait_exists()` on the
 * covered FQCN — so an unresolvable name (a fixture stub, a class not on the autoloader) falls back to
 * `CoversClass`, the safe default. Arguments are preserved verbatim and the replacement name is emitted
 * fully-qualified so no import management is required; unrelated class attributes are left in place.
 */
#[TestRectorFixtures('CoversToCoversClassRector')]
final class CoversToCoversClassRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert class-level `#[Testo\Codecov\Covers(X::class)]` into `#[\PHPUnit\Framework\Attributes\CoversClass(X::class)]`',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Codecov\Covers(Foo::class)]
                        class FooTest {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\CoversClass(Foo::class)]
                        class FooTest {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $changed = false;
        $keptGroups = [];

        foreach ($node->attrGroups as $attrGroup) {
            $keptAttrs = [];

            foreach ($attrGroup->attrs as $attribute) {
                if (!$this->isName($attribute->name, 'Testo\\Codecov\\Covers')) {
                    $keptAttrs[] = $attribute;
                    continue;
                }

                $replacement = $this->phpunitAttributeFor($this->coveredTarget($attribute));

                // An interface has no PHPUnit coverage target — drop the attribute entirely.
                if ($replacement === null) {
                    $changed = true;
                    continue;
                }

                $attribute->name = new FullyQualified('PHPUnit\\Framework\\Attributes\\' . $replacement);
                $keptAttrs[] = $attribute;
                $changed = true;
            }

            // Trim the group in place (keeps its source position); a group emptied because its only
            // attribute covered an interface is removed.
            if ($keptAttrs === []) {
                $changed = $changed || $attrGroup->attrs !== [];
                continue;
            }

            $attrGroup->attrs = $keptAttrs;
            $keptGroups[] = $attrGroup;
        }

        if (!$changed) {
            return null;
        }

        $node->attrGroups = $keptGroups;

        return $node;
    }

    /**
     * The fully-qualified name of the class-constant the `Covers` attribute targets, or null.
     */
    private function coveredTarget(Attribute $attribute): ?string
    {
        $value = ($attribute->args[0] ?? null)?->value;

        return $value instanceof ClassConstFetch ? $this->getName($value->class) : null;
    }

    /**
     * The PHPUnit attribute short name for a covered target, or null when it must be dropped:
     * a trait maps to `CoversTrait`, an interface is dropped, and everything else (a class or enum, or
     * a name that cannot be resolved) maps to `CoversClass`.
     */
    private function phpunitAttributeFor(?string $fqcn): ?string
    {
        if ($fqcn === null) {
            return 'CoversClass';
        }

        if (\interface_exists($fqcn)) {
            return null;
        }

        return \trait_exists($fqcn) ? 'CoversTrait' : 'CoversClass';
    }
}
