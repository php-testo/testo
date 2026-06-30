<?php

declare(strict_types=1);

namespace Testo\PhpUnitBuild\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Build-only local rule: run every test of a class that had a `__construct()`/`__destruct()` in its
 * own process (`#[RunTestsInSeparateProcesses]` + `#[PreserveGlobalState(false)]`).
 *
 * In Testo a test class is instantiated once per case and the framework isolates state between
 * tests; such tests therefore assume a clean slate per run. Under PHPUnit's single shared process
 * that assumption breaks — static state set through the engine (e.g. lifecycle counters driven by
 * TestRunner) accumulates across the class's tests and makes "ran exactly once" assertions fail. A
 * fresh subprocess per test restores Testo's isolation.
 *
 * Detection keys on the marker that {@see \Testo\Bridge\Rector\TestoToPhpunit\ConstructorDestructorToLifecycleRector}
 * leaves — a `setUpFromConstructor*` / `tearDownFromDestructor*` method — so it must run after it.
 */
final class IsolateConstructorTestsRector extends AbstractRector
{
    private const MARKER_PREFIXES = ['setUpFromConstructor', 'tearDownFromDestructor'];

    private const RUN_IN_SEPARATE_PROCESSES = 'PHPUnit\\Framework\\Attributes\\RunTestsInSeparateProcesses';
    private const PRESERVE_GLOBAL_STATE = 'PHPUnit\\Framework\\Attributes\\PreserveGlobalState';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Run tests of a former-constructor/destructor class in separate processes to restore Testo state isolation',
            [
                new CodeSample(
                    <<<'PHP'
                        class SomeTest extends \PHPUnit\Framework\TestCase
                        {
                            #[\PHPUnit\Framework\Attributes\Before]
                            protected function setUpFromConstructor(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
                        #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
                        class SomeTest extends \PHPUnit\Framework\TestCase
                        {
                            #[\PHPUnit\Framework\Attributes\Before]
                            protected function setUpFromConstructor(): void {}
                        }
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
        if ($node->extends === null
            || !$this->isName($node->extends, 'PHPUnit\\Framework\\TestCase')
            || !$this->hasConstructorMarker($node)
            || $this->hasAttribute($node, self::RUN_IN_SEPARATE_PROCESSES)
        ) {
            return null;
        }

        $node->attrGroups[] = new AttributeGroup([new Attribute(new FullyQualified(self::RUN_IN_SEPARATE_PROCESSES))]);
        $node->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified(self::PRESERVE_GLOBAL_STATE), [new Arg(new ConstFetch(new Name('false')))]),
        ]);

        return $node;
    }

    private function hasConstructorMarker(Class_ $node): bool
    {
        foreach ($node->getMethods() as $method) {
            $name = (string) $this->getName($method);
            foreach (self::MARKER_PREFIXES as $prefix) {
                if (\str_starts_with($name, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasAttribute(Class_ $node, string $fqcn): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, $fqcn)) {
                    return true;
                }
            }
        }

        return false;
    }
}
