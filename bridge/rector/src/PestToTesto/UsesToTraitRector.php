<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: handle Pest's `uses(SomeTestCase::class, SomeTrait::class)` file binding.
 *
 * @todo NOT IMPLEMENTED — no faithful target. {@see TestCallToFunctionRector} turns Pest's
 *       file-level `test()`/`it()` calls into free FUNCTIONS, and a function has no base class,
 *       no traits, and no `$this`.
 *
 * `uses(...)` binds a base TestCase and/or traits to every test in the FILE, and is what gives Pest
 * closures their `$this` (helpers, properties, shared setup). A converted Testo function cannot host
 * any of that: `extends`/`use` are class-only, and the bound `$this` simply has no analogue on a
 * function. The mapping is also not one-to-one — a Pest TestCase often carries Pest-specific
 * lifecycle plumbing with no Testo counterpart. So `uses()` is left untouched (visibly unconverted)
 * and, because a closure that captures `$this`-shared state cannot become a function either,
 * {@see TestCallToFunctionRector} deliberately bails on the tests that rely on it.
 *
 * MANUAL WORK: re-express the shared base/traits by hand — promote shared setup into
 * `Testo\Lifecycle\*` hooks and shared state into a form the ported functions can read; port any
 * trait helpers to plain functions. Drop the `uses()` call entirely.
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
