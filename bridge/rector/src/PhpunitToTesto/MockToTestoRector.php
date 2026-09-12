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
 * The convertible mock forms now have a target API — the Double bridge (`testo/bridge-double`) — and
 * are handled by the registered {@see CreateMockToDoubleRector}: `createMock()`/`createStub()` and
 * their `expects()`/`method()`/`will*()` chains. This stub documents only what stays out of reach.
 *
 * @todo No faithful automatic conversion for the residual forms: Prophecy's `prophesize()` (a
 *   different creation/expectation model); a `getMockBuilder()` chain carrying a builder step beyond
 *   `disableOriginalConstructor()` (`onlyMethods`, `setConstructorArgs`, `getMockForAbstractClass`, …)
 *   or the bare constructor-calling `getMockBuilder(X)->getMock()`, all of which change what is
 *   doubled; the return shape `willReturnMap()`; a variable invocation matcher; and the `with()`
 *   constraints that have no `Argument::*` equivalent (`stringContains()` — substring, vs Double's
 *   iterable-only `contains`; `greaterThan()`/`lessThan()`, and `logicalOr()`/`logicalAnd()`/
 *   `logicalNot()` composites — the same gap as `assertThat`). Migrate these by hand: the matching
 *   `\JMac\Testing\Double` / `Argument::*` form, a standalone mocking library (Mockery,
 *   phpspec/prophecy), or a hand-written fake. This rule exists only to document the gap; it never
 *   modifies code.
 */
final class MockToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: mock forms with no faithful Double target (prophesize/willReturnMap/builder-with-extra-steps/with-constraints) — manual migration required (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = $this->getMockBuilder(Dependency::class)->onlyMethods(['run'])->getMock();
                        PHP,
                    <<<'PHP'
                        // No faithful Double target: migrate by hand (see CreateMockToDoubleRector for the forms that do convert).
                        $dep = $this->getMockBuilder(Dependency::class)->onlyMethods(['run'])->getMock();
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
