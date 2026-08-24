<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites `Testo\Assert::*` static calls into PHPUnit `$this->assert*` method calls.
 *
 * Argument order differs between the two facades and MUST be corrected, otherwise
 * the converted assertion changes meaning:
 *   - Testo:   Assert::same($actual, $expected[, $message])
 *   - PHPUnit: $this->assertSame($expected, $actual[, $message])
 * Comparison assertions therefore swap the first two arguments; the trailing
 * `$message` keeps its position (it is last in both APIs).
 *
 * Only rewrites a call that lives inside a class (a test method or a `static` data provider) — the
 * only place a PHPUnit assertion makes sense. A `Testo\Assert::*` call in a free function or at
 * namespace level is left untouched: neither `$this->` nor `self::` would be valid there, so
 * converting it would emit code that fatals.
 *
 * Inside a class the call form follows the enclosing scope: `$this->assert*` in instance methods (the
 * common case), but `self::assert*` wherever `$this` is unavailable — a `static` helper, a static
 * closure or a `public static` data provider. PHPUnit's assertions are static methods, so `self::`
 * is valid in any class scope; emitting `$this->` in a static scope would be a fatal "using $this
 * outside object context".
 *
 * Methods with no faithful PHPUnit counterpart (fluent type assertions such as
 * `Assert::string()`/`int()`/`json()`, and `blank()`/`notBlank()`) are intentionally left
 * untouched, so the surrounding test stays visibly unconverted instead of being
 * silently mistranslated. (`blank()`/`notBlank()` deliberately treat `false`/`0`/`'0'` as valid
 * data, unlike PHPUnit's `assertEmpty`/`assertNotEmpty`, so a blind swap would change meaning.)
 */
#[TestRectorFixtures('AssertCallToPhpUnitRector')]
final class AssertCallToPhpUnitRector extends AbstractRector
{
    /**
     * Testo Assert method => [PHPUnit method, swap first two arguments?].
     *
     * @var array<non-empty-string, array{non-empty-string, bool}>
     */
    private const MAP = [
        'same' => ['assertSame', true],
        'notSame' => ['assertNotSame', true],
        'equals' => ['assertEquals', true],
        'notEquals' => ['assertNotEquals', true],
        'true' => ['assertTrue', false],
        'false' => ['assertFalse', false],
        'null' => ['assertNull', false],
        'notNull' => ['assertNotNull', false],
        'count' => ['assertCount', true],
        'contains' => ['assertContains', true],
        'instanceOf' => ['assertInstanceOf', true],
        'fail' => ['fail', false],
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert Testo\Assert::* static calls into PHPUnit $this->assert* calls (swapping actual/expected order)',
            [
                new CodeSample(
                    <<<'PHP'
                        \Testo\Assert::same($result, 3);
                        PHP,
                    <<<'PHP'
                        $this->assertSame(3, $result);
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node->class, 'Testo\\Assert')) {
            return null;
        }

        $method = $this->getName($node->name);
        if ($method === null || !isset(self::MAP[$method])) {
            return null;
        }

        [$phpunitMethod, $swap] = self::MAP[$method];

        # Only convert inside a class: `$this->`/`self::` have no valid target in a free function or
        # at namespace level, so such a call is left as-is rather than mistranslated into a fatal.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        $args = $node->args;
        if ($swap && \count($args) >= 2 && $args[0] instanceof Arg && $args[1] instanceof Arg) {
            [$args[0], $args[1]] = [$args[1], $args[0]];
        }

        return $this->isThisAvailable($node)
            ? new MethodCall(new Variable('this'), new Identifier($phpunitMethod), $args)
            : new StaticCall(new Name('self'), new Identifier($phpunitMethod), $args);
    }

    /**
     * Whether `$this` is bound in the scope of $node — false inside a static method, a static
     * closure or a free function, where the assertion must be emitted as `self::assert*`.
     */
    private function isThisAvailable(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->hasVariableType('this')->yes();
    }

    /**
     * Whether $node sits inside a class (test method, data provider, …). Outside a class — a free
     * function or namespace-level code — there is no valid `$this->`/`self::` target, so the call is
     * left unchanged instead of being converted into code that would fatal.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
