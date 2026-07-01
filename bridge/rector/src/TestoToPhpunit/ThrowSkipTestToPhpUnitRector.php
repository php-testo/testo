<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites `throw new Testo\Core\Exception\SkipTest($message)` into a PHPUnit
 * `$this->markTestSkipped($message)` statement.
 *
 * Testo signals a skip by throwing {@see \Testo\Core\Exception\SkipTest}; PHPUnit
 * signals the same intent with a `markTestSkipped()` call. Because the throw is a
 * full statement, the rewrite replaces the whole `Throw_` statement with an
 * expression statement wrapping the method call (PHPUnit's `markTestSkipped()`
 * itself throws internally, so dropping the `throw` keyword preserves behaviour).
 *
 * The optional message argument is forwarded; a message-less skip becomes a
 * bare `$this->markTestSkipped()` call. Throws of any other class are left
 * untouched.
 *
 * Only rewrites a throw that lives inside a class: `$this->markTestSkipped()` needs a method scope,
 * so a `throw new SkipTest(...)` in a free function or at namespace level is left as-is.
 */
#[TestRectorFixtures('ThrowSkipTestToPhpUnitRector')]
final class ThrowSkipTestToPhpUnitRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert `throw new Testo\Core\Exception\SkipTest($m)` into `$this->markTestSkipped($m)`',
            [
                new CodeSample(
                    <<<'PHP'
                        throw new \Testo\Core\Exception\SkipTest('not applicable');
                        PHP,
                    <<<'PHP'
                        $this->markTestSkipped('not applicable');
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Throw_::class];
    }

    /**
     * @param Throw_ $node The `throw` expression (PHP 8 throw-as-expression); the enclosing
     *        statement keeps wrapping the replacement, yielding `$this->markTestSkipped(...);`.
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $thrown = $node->expr;
        if (!$thrown instanceof New_) {
            return null;
        }

        if (!$this->isName($thrown->class, 'Testo\\Core\\Exception\\SkipTest')) {
            return null;
        }

        # Only convert inside a class: `$this->markTestSkipped()` has no valid target in a free
        # function or at namespace level, so such a throw is left unchanged.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        return new MethodCall(
            new Variable('this'),
            new Identifier('markTestSkipped'),
            $thrown->args,
        );
    }

    /**
     * Whether $node sits inside a class. Outside one — a free function or namespace-level code — the
     * emitted `$this->markTestSkipped()` call would have no valid target, so the throw is left as-is.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
