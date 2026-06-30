<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: convert Pest lifecycle hooks `beforeEach()`, `afterEach()`,
 * `beforeAll()`, `afterAll()` into Testo lifecycle methods annotated with
 * `#[\Testo\Lifecycle\BeforeTest]`, `#[\Testo\Lifecycle\AfterTest]`,
 * `#[\Testo\Lifecycle\BeforeClass]`, `#[\Testo\Lifecycle\AfterClass]`.
 *
 * @todo NOT IMPLEMENTED — blocked by the same missing class as
 *       {@see TestFunctionToMethodRector}.
 *
 * Pest hooks are file-level closure calls with no enclosing class, and the
 * closure body binds `$this` to a per-file TestCase proxy with shared state set
 * up via `uses()`. A Testo lifecycle hook is a METHOD on the generated test
 * class, sharing state through `$this` properties. Until a host class exists
 * (which Rector cannot synthesize, see {@see TestFunctionToMethodRector}), there
 * is nowhere to attach these methods and no defined `$this` to rebind to.
 *
 * MANUAL WORK: after creating the test class, move each hook closure body into a
 * method and annotate it with the matching `Testo\Lifecycle\*` attribute
 * (`beforeEach` -> `BeforeTest`, `afterEach` -> `AfterTest`,
 * `beforeAll` -> `BeforeClass`, `afterAll` -> `AfterClass`). Promote any
 * cross-hook shared state to class properties.
 */
final class LifecycleHookToMethodRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `beforeEach`/`afterEach`/`beforeAll`/`afterAll` hooks into Testo `Lifecycle\*` methods. Blocked by the missing host class; see PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        beforeEach(function () {
                            $this->service = new Service();
                        });
                        PHP,
                    <<<'PHP'
                        #[\Testo\Lifecycle\BeforeTest]
                        public function setUpService(): void
                        {
                            $this->service = new Service();
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
