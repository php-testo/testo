<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * INTENT: turn a Pest `test('name', fn () => ...)` / `it('name', fn () => ...)`
 * file-level call into a `#[\Testo\Test]` method on a test class.
 *
 * @todo NOT IMPLEMENTED — this is the core blocker for the whole Pest -> Testo
 *       direction and cannot be done cleanly by Rector.
 *
 * Rector rewrites an existing AST in place; it does not synthesize new program
 * structure. Pest is a functional DSL where tests are top-level `test()`/`it()`
 * calls with NO enclosing class. Converting them to Testo requires:
 *
 *   1. Inventing a class to host the tests (Testo tests live as `#[Test]` methods
 *      on a class). There is no source class to attach to, so a name, namespace,
 *      and PSR-4 file location would all have to be fabricated and kept consistent
 *      with the file path.
 *   2. Deriving a valid PHP method name from a free-form description string
 *      (`test('it adds two numbers')` -> `itAddsTwoNumbers`?), with no guaranteed
 *      uniqueness or reversibility.
 *   3. Rebinding `$this`. Pest closures resolve `$this` to a per-file TestCase
 *      proxy and pull in helpers via `uses()`; a Testo method binds `$this` to the
 *      generated class. Closures may also `use (...)` outer variables that have no
 *      home on a method.
 *   4. Hoisting every sibling file-level statement (`beforeEach`, `uses`, helper
 *      functions, dataset definitions) into the same generated class coherently.
 *
 * These are whole-file restructuring decisions, not local node rewrites, so this
 * rule intentionally does nothing and is NOT registered in any set.
 *
 * MANUAL WORK: create a test class (e.g. `final class FooTest`), move each
 * `test()`/`it()` closure body into a method named after the case, annotate the
 * method (or the class) with `#[\Testo\Test]`, and convert the body's assertions
 * with {@see ExpectToAssertRector}.
 */
final class TestFunctionToMethodRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'INTENT (not implemented): convert Pest `test()`/`it()` file-level calls into `#[\Testo\Test]` class methods. Functional-to-class restructuring is intractable for Rector; see the class @todo and PestToTesto/TODO.md.',
            [
                new CodeSample(
                    <<<'PHP'
                        test('adds numbers', function () {
                            expect(1 + 1)->toBe(2);
                        });
                        PHP,
                    <<<'PHP'
                        final class ExampleTest
                        {
                            #[\Testo\Test]
                            public function addsNumbers(): void
                            {
                                \Testo\Assert::same(1 + 1, 2);
                            }
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
