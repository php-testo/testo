<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites PHPUnit `$this->assert*` (also `self::`/`static::`) calls into
 * `Testo\Assert::*` static calls.
 *
 * Argument order differs between the two facades and MUST be corrected, otherwise
 * the converted assertion changes meaning:
 *   - PHPUnit: $this->assertSame($expected, $actual[, $message])
 *   - Testo:   Assert::same($actual, $expected[, $message])
 * Comparison assertions therefore swap the first two arguments; the trailing
 * `$message` keeps its position (it is last in both APIs).
 *
 * Assertions with no faithful Testo counterpart (e.g. `assertThat` which relies on
 * PHPUnit constraint objects) are intentionally left untouched, so the surrounding
 * test stays visibly unconverted instead of being silently mistranslated.
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
        return [MethodCall::class, StaticCall::class];
    }

    /**
     * @param MethodCall|StaticCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        if ($node instanceof MethodCall) {
            if (!$this->isName($node->var, 'this')) {
                return null;
            }
        } else {
            if (!$this->isName($node->class, 'self') && !$this->isName($node->class, 'static')) {
                return null;
            }
        }

        $method = $this->getName($node->name);
        if ($method === null || !isset(self::MAP[$method])) {
            return null;
        }

        [$testoMethod, $swap] = self::MAP[$method];

        $args = $node->args;
        if ($swap && \count($args) >= 2 && $args[0] instanceof Arg && $args[1] instanceof Arg) {
            [$args[0], $args[1]] = [$args[1], $args[0]];
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($testoMethod), $args);
    }
}
