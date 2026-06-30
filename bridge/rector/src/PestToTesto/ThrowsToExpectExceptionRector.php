<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert a Pest `test(...)->throws(SomeException::class)` chain into a
 * Testo `\Testo\Expect::exception(SomeException::class)` expectation.
 *
 * @todo NOT IMPLEMENTED — depends on the surrounding test having become a method,
 *       which Rector cannot do (see {@see TestFunctionToMethodRector}).
 *
 * The `->throws(...)` chain hangs off a top-level `test()` PendingTest object; it
 * declares an expectation about the WHOLE test body, not a local expression.
 * Testo's `Expect::exception()` is a statement placed INSIDE the test method body
 * (before the throwing call), or it pairs with an attribute. Lifting `->throws()`
 * out of the fluent chain and into the right position inside a method body cannot
 * be expressed as a local node rewrite while the test is still a file-level
 * `test()` call with no method body to insert into.
 *
 * MANUAL WORK: after the test is a method, drop the `->throws(X)` chain and add
 * `\Testo\Expect::exception(X)` before the code expected to throw (or assert on
 * the thrown exception per Testo's exception API).
 */
final class ThrowsToExpectExceptionRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `->throws(X)` into `\Testo\Expect::exception(X)`. Requires the test to be a method body; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        test('fails', function () {
                            doThing();
                        })->throws(RuntimeException::class);
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        public function fails(): void
                        {
                            \Testo\Expect::exception(RuntimeException::class);
                            doThing();
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Node::class];
    }

    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // Intentionally a no-op: see the class-level @todo.
        return null;
    }
}
