<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert a Pest `test(...)->group('a', 'b')` chain into a Testo
 * `#[\Testo\Filter\Group('a', 'b')]` attribute on the test method or class.
 *
 * @todo NOT IMPLEMENTED — depends on the surrounding test having become a method,
 *       which Rector cannot do (see {@see TestFunctionToMethodRector}).
 *
 * `->group(...)` is a fluent modifier on the top-level `test()` PendingTest.
 * Testo groups are declared as an ATTRIBUTE on a method (or class). An attribute
 * cannot be attached while the test is still a file-level `test()` call: there is
 * no method/class declaration node to annotate. Pest's file-wide grouping via
 * `uses()->group(...)` would similarly map to a class-level attribute that does
 * not yet exist.
 *
 * MANUAL WORK: after the test is a method, drop the `->group(...)` chain and add
 * `#[\Testo\Filter\Group('a', 'b')]` on the method (or on the class for file-wide
 * groups).
 */
final class GroupChainToGroupAttributeRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `->group(...)` into `#[\Testo\Filter\Group(...)]`. Requires a method/class to annotate; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        test('slow path', function () {
                            // ...
                        })->group('integration');
                        PHP,
                    <<<'PHP'
                        #[\Testo\Test]
                        #[\Testo\Filter\Group('integration')]
                        public function slowPath(): void
                        {
                            // ...
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
