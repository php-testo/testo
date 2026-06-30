<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: handle Pest's `uses(SomeTestCase::class, SomeTrait::class)` file
 * binding when restructuring into a Testo class.
 *
 * @todo NOT IMPLEMENTED — depends on the host test class existing, which Rector
 *       cannot synthesize (see {@see TestFunctionToMethodRector}).
 *
 * `uses(...)` binds a base TestCase and/or traits to every test in the FILE, and
 * is what gives Pest closures their `$this` (helpers, properties, shared setup).
 * Testo has no file-level binding: a class explicitly `extends` a base and `use`s
 * traits in its body. Translating `uses()` therefore means emitting `extends` /
 * `use` clauses on the generated class — which does not exist yet — and the
 * mapping is not even one-to-one (a Pest TestCase often carries Pest-specific
 * lifecycle plumbing that has no Testo analogue and must be reworked by hand).
 *
 * MANUAL WORK: when creating the test class, add the appropriate `extends` for a
 * shared base (porting its helpers/lifecycle to Testo equivalents) and `use` the
 * relevant traits in the class body. Drop the `uses()` call entirely.
 */
final class UsesToTraitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): translate Pest `uses(...)` file binding into class `extends`/`use`. Requires the host class; mapping is not one-to-one. See PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        uses(TestCase::class, RefreshDatabase::class);
                        PHP,
                    <<<'PHP'
                        final class ExampleTest extends TestCase
                        {
                            use RefreshDatabase;
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
