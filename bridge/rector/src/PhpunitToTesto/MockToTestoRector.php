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
 * Intended target: PHPUnit/Prophecy mock creation — `createMock()`,
 * `getMockBuilder()`, `createStub()`, `createMockForIntersectionOfInterfaces()`,
 * `prophesize()`.
 *
 * @todo Unconvertible automatically. Testo ships NO built-in mocking/doubling
 *   facility — there is no target API to rewrite these calls into. The expectation
 *   model (PHPUnit's `->expects()->method()->willReturn()`, Prophecy's promises and
 *   `reveal()`) has no Testo equivalent, so there is nothing to map onto.
 *   Migration must be done manually: introduce a standalone mocking library
 *   (e.g. Mockery, phpspec/prophecy used directly) or hand-write fakes/test doubles.
 *   This rule exists only to document the gap; it never modifies code.
 */
final class MockToTestoRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'STUB: PHPUnit/Prophecy mocks have no Testo equivalent — manual migration required (see @todo)',
            [
                new CodeSample(
                    <<<'PHP'
                        $dep = $this->createMock(Dependency::class);
                        PHP,
                    <<<'PHP'
                        // No Testo equivalent: replace with a manual fake or a third-party mocking library.
                        $dep = $this->createMock(Dependency::class);
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
