<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites Testo lifecycle method attributes into their PHPUnit attribute equivalents:
 *   - `Testo\Lifecycle\BeforeTest`  => `\PHPUnit\Framework\Attributes\Before`
 *   - `Testo\Lifecycle\AfterTest`   => `\PHPUnit\Framework\Attributes\After`
 *   - `Testo\Lifecycle\BeforeClass` => `\PHPUnit\Framework\Attributes\BeforeClass`
 *   - `Testo\Lifecycle\AfterClass`  => `\PHPUnit\Framework\Attributes\AfterClass`
 *
 * Only the attribute class is swapped; any arguments (e.g. Testo's `priority`)
 * are preserved verbatim — PHPUnit ignores unknown arguments so the rewrite stays
 * faithful for the common no-argument case. The replacement names are emitted
 * fully-qualified so no import management is required. Unrelated method attributes
 * are left in place.
 *
 * Exception: when the method is already named like the PHPUnit template hook for that phase
 * (`setUp`/`tearDown`/`setUpBeforeClass`/`tearDownAfterClass`), the attribute is DROPPED rather than
 * converted. PHPUnit runs those by name, so a `#[Before]`/`#[After]`/… on top is redundant and makes
 * the runner emit a warning ("… is a template method and does not need the … attribute"). Dropping it
 * is behaviour-preserving: the method still runs in the same phase, now by name.
 */
#[TestRectorFixtures('LifecycleAttributesToPhpUnitRector')]
final class LifecycleAttributesToPhpUnitRector extends AbstractRector
{
    /**
     * Testo lifecycle attribute FQN => PHPUnit attribute FQN.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const MAP = [
        'Testo\\Lifecycle\\BeforeTest' => 'PHPUnit\\Framework\\Attributes\\Before',
        'Testo\\Lifecycle\\AfterTest' => 'PHPUnit\\Framework\\Attributes\\After',
        'Testo\\Lifecycle\\BeforeClass' => 'PHPUnit\\Framework\\Attributes\\BeforeClass',
        'Testo\\Lifecycle\\AfterClass' => 'PHPUnit\\Framework\\Attributes\\AfterClass',
    ];

    /**
     * Testo lifecycle attribute FQN => the PHPUnit template method that covers the same phase by name.
     * A method with this name needs no attribute (PHPUnit would warn about the redundant one).
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const TEMPLATE_METHOD = [
        'Testo\\Lifecycle\\BeforeTest' => 'setup',
        'Testo\\Lifecycle\\AfterTest' => 'teardown',
        'Testo\\Lifecycle\\BeforeClass' => 'setupbeforeclass',
        'Testo\\Lifecycle\\AfterClass' => 'teardownafterclass',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo\Lifecycle\* method attributes into PHPUnit lifecycle attributes (Before/After/BeforeClass/AfterClass)',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Lifecycle\BeforeTest]
                        public function prepare(): void {}
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Before]
                        public function prepare(): void {}
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $methodName = \strtolower((string) $this->getName($node));
        $changed = false;
        $keptGroups = [];

        foreach ($node->attrGroups as $attrGroup) {
            $keptAttrs = [];

            foreach ($attrGroup->attrs as $attribute) {
                $testo = $this->matchedLifecycle($attribute->name);

                if ($testo === null) {
                    $keptAttrs[] = $attribute;
                    continue;
                }

                // Already named like the template hook for this phase: PHPUnit runs it by name, so the
                // attribute is redundant — drop it.
                if (self::TEMPLATE_METHOD[$testo] === $methodName) {
                    $changed = true;
                    continue;
                }

                $attribute->name = new FullyQualified(self::MAP[$testo]);
                $keptAttrs[] = $attribute;
                $changed = true;
            }

            // Keep the group (mutating its attrs in place preserves its source position); a group left
            // empty because its sole lifecycle attribute was dropped is removed entirely.
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

    /** The Testo lifecycle FQN this attribute names, or null when it is not a lifecycle attribute. */
    private function matchedLifecycle(Node\Name $name): ?string
    {
        foreach (self::MAP as $testo => $_phpunit) {
            if ($this->isName($name, $testo)) {
                return $testo;
            }
        }

        return null;
    }
}
