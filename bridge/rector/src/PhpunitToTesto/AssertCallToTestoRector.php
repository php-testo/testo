<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitAssertionCall;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites PHPUnit `$this->assert*` (also `self::`/`static::`, and the `PHPUnit\Framework\assert*()`
 * functions) calls into `Testo\Assert::*` static calls.
 *
 * Argument order differs between the two facades and MUST be corrected, otherwise
 * the converted assertion changes meaning:
 *   - PHPUnit: $this->assertSame($expected, $actual[, $message])
 *   - Testo:   Assert::same($actual, $expected[, $message])
 * Comparison assertions therefore swap the first two arguments; the trailing
 * `$message` keeps its position (it is last in both APIs).
 *
 * `assertNotTrue($x)`/`assertNotFalse($x)` have no flat counterpart and become
 * `Assert::notSame($x, true)`/`Assert::notSame($x, false)`: PHPUnit negates an `=== true`/`=== false`
 * check, so any other value, `1` and `0` included, passes on both sides.
 *
 * Assertions with no faithful Testo counterpart (e.g. `assertThat` which relies on
 * PHPUnit constraint objects) are intentionally left untouched, so the surrounding
 * test stays visibly unconverted instead of being silently mistranslated.
 *
 * Only rewrites a call that lives inside a class (a test method or a `static` data provider) — the
 * only place a PHPUnit assertion belongs. A matching call in a free function or at namespace level
 * is left untouched.
 */
#[TestRectorFixtures('AssertCallToTestoRector')]
final class AssertCallToTestoRector extends AbstractRector
{
    /**
     * PHPUnit assertion => [Testo Assert method, swap first two arguments?].
     *
     * @var array<non-empty-string, array{non-empty-string, bool}>
     */
    private const MAP = [
        'assertSame' => ['same', true],
        'assertNotSame' => ['notSame', true],
        'assertEquals' => ['equals', true],
        'assertNotEquals' => ['notEquals', true],
        'assertTrue' => ['true', false],
        'assertFalse' => ['false', false],
        'assertNull' => ['null', false],
        'assertNotNull' => ['notNull', false],
        'assertCount' => ['count', true],
        'assertContains' => ['contains', true],
        'assertInstanceOf' => ['instanceOf', true],
        'fail' => ['fail', false],
    ];

    /**
     * PHPUnit negated boolean assertion => the literal `Assert::notSame()` compares against.
     *
     * @var array<non-empty-string, 'true'|'false'>
     */
    private const NOT_BOOL = [
        'assertNotTrue' => 'true',
        'assertNotFalse' => 'false',
    ];

    public function __construct(
        private readonly PhpunitAssertionCall $assertionCall,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit $this->assert* calls into Testo\Assert::* static calls (swapping expected/actual order)',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->assertSame(3, $result);
                        PHP,
                    <<<'PHP'
                        \Testo\Assert::same($result, 3);
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return PhpunitAssertionCall::NODE_TYPES;
    }

    /**
     * @param MethodCall|StaticCall|FuncCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        $method = $this->assertionCall->name($node);
        if ($method === null) {
            return null;
        }

        if (isset(self::NOT_BOOL[$method])) {
            return $this->notBool(self::NOT_BOOL[$method], $node->args);
        }

        if (!isset(self::MAP[$method])) {
            return null;
        }

        [$testoMethod, $swap] = self::MAP[$method];

        $args = $node->args;
        if ($swap && \count($args) >= 2 && $args[0] instanceof Arg && $args[1] instanceof Arg) {
            [$args[0], $args[1]] = [$args[1], $args[0]];
        }

        return new StaticCall(new FullyQualified('Testo\Assert'), new Identifier($testoMethod), $args);
    }

    /**
     * `assertNotTrue($subject[, $message])` → `Assert::notSame($subject, true[, $message])`.
     *
     * @param 'true'|'false' $literal
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function notBool(string $literal, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        $callArgs = [$subject, new Arg(new ConstFetch(new Name($literal)))];
        if (($args[1] ?? null) instanceof Arg) {
            $callArgs[] = $args[1];
        }

        return new StaticCall(new FullyQualified('Testo\Assert'), new Identifier('notSame'), $callArgs);
    }
}
