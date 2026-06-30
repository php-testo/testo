<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert a Pest `test(...)->skip()` chain into a Testo skip, i.e.
 * `throw new \Testo\Core\Exception\SkipTest($reason)` inside the test body.
 *
 * @todo NOT IMPLEMENTED — depends on the surrounding test having become a method,
 *       which Rector cannot do (see {@see TestFunctionToMethodRector}).
 *
 * `->skip()` is a fluent modifier on the top-level `test()` PendingTest. Testo
 * skips by THROWING `SkipTest` from within the test method body. There is no
 * method body to inject the throw into while the test remains a file-level
 * `test()` call, and a conditional `->skip($condition)` would additionally need
 * to become an `if ($condition) { throw ...; }` guard at the top of the method.
 *
 * MANUAL WORK: after the test is a method, replace the `->skip(...)` chain with
 * `throw new \Testo\Core\Exception\SkipTest('reason');` at the start of the body
 * (wrap in an `if` when the skip was conditional).
 */
final class SkipChainToSkipTestRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `->skip()` into `throw new \Testo\Core\Exception\SkipTest(...)`. Requires the test to be a method body; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        test('wip', function () {
                            // ...
                        })->skip('not ready');
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        public function wip(): void
                        {
                            throw new \Testo\Core\Exception\SkipTest('not ready');
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
