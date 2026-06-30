<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered.
 *
 * Intended behavior: remove `extends PHPUnit\Framework\TestCase` from a test class
 * and mark it as a Testo test (e.g. class-level `#[\Testo\Test]`).
 *
 * @todo Implement. Structural challenges that make a faithful, automatic conversion hard:
 *   - Test discovery differs fundamentally. PHPUnit discovers any public method whose
 *     name starts with `test` (plus `#[Test]`-annotated methods); Testo discovers tests
 *     by the `#[\Testo\Test]` attribute (and `#[TestInline]` cases). Simply dropping the
 *     base class would orphan every `testFoo()` method, because Testo would no longer
 *     consider them tests. A correct rule must either add `#[\Testo\Test]` to every
 *     `test`-prefixed/`#[PHPUnit\...\Test]` method, or rename them — both invasive.
 *   - The `test` name prefix carries semantics in PHPUnit but none in Testo, so any
 *     prefix-based heuristic risks both false positives (helpers named `testHelper`)
 *     and false negatives.
 *   - Inherited assertion/lifecycle behavior provided by TestCase disappears; the body
 *     must already have been converted (assertions, lifecycle, expectException) for the
 *     class to function without the base class. Ordering this rule against the others is
 *     itself non-trivial.
 *   - Abstract intermediate base classes and shared test traits complicate "extends"
 *     detection.
 */
final class ExtendsTestCaseToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: remove "extends PHPUnit\\Framework\\TestCase" and mark the class as a Testo test (see @todo for blockers)',
            [
                new CodeSample(
                    <<<'PHP'
                        final class MyTest extends \PHPUnit\Framework\TestCase
                        {
                            public function testFoo(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        final class MyTest
                        {
                            #[\Testo\Test]
                            public function testFoo(): void {}
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node\Stmt\Class_::class];
    }

    /**
     * @param Node\Stmt\Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Not implemented — see class-level @todo.
        return null;
    }
}
