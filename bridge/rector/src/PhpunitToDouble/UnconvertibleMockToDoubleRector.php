<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToDouble;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * STUB — not implemented, not registered.
 *
 * The convertible mock forms are handled by the registered {@see CreateMockToDoubleRector}. This stub
 * documents only what stays out of reach.
 *
 * @todo No faithful automatic conversion for the residual forms: Prophecy's `prophesize()` (a
 *   different creation/expectation model); `getMockForAbstractClass()`/`getMockForTrait()` (the abstract
 *   methods need doubling, which depends on the class); `addMethods()`, `setMockClassName()` and the
 *   bare constructor-calling `getMockBuilder(X)->getMock()`; a partial double with
 *   `disableAutoReturnValueGeneration()` (Double has no per-method strictness); a configured or partial
 *   double that is not assigned to a variable or property (its `allows()` calls need statements of their
 *   own); a variable invocation matcher; `withConsecutive()`; `willReturnReference()`; and the `with()`
 *   constraints with no fixed form: `stringContains()` with a computed case flag or line-ending
 *   normalisation, `isType()`/`containsOnly()` with a computed or unknown type, `matches()` (format
 *   descriptions). Migrate these by hand: the matching `\JMac\Testing\Double` / `Argument::*` form, a
 *   standalone mocking library (Mockery, phpspec/prophecy), or a hand-written fake. This rule exists only
 *   to document the gap; it never modifies code.
 */
final class UnconvertibleMockToDoubleRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: mock forms with no faithful Double target (prophesize/getMockForAbstractClass/addMethods/withConsecutive/strict partials) — manual migration required (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->getMockForAbstractClass();
                        PHP,
                    <<<'PHP'
                        // No faithful Double target: migrate by hand (see CreateMockToDoubleRector for the forms that do convert).
                        $dep = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->getMockForAbstractClass();
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node\Expr\MethodCall::class];
    }

    /**
     * @param Node\Expr\MethodCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Not implemented — see class-level @todo.
        return null;
    }
}
