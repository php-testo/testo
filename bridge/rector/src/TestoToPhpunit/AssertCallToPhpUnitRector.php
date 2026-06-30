<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
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
 * Methods with no faithful PHPUnit counterpart (fluent type assertions such as
 * `Assert::string()`/`int()`/`json()`, and `blank()`) are intentionally left
 * untouched, so the surrounding test stays visibly unconverted instead of being
 * silently mistranslated.
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

        $args = $node->args;
        if ($swap && \count($args) >= 2 && $args[0] instanceof Arg && $args[1] instanceof Arg) {
            [$args[0], $args[1]] = [$args[1], $args[0]];
        }

        return new MethodCall(new Variable('this'), new Identifier($phpunitMethod), $args);
    }
}
