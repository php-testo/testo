<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PestToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites a single Pest expectation `expect($value)->toX(...)` into the matching
 * `\Testo\Assert::*` static call.
 *
 * Pest's expectation API is value-first and fluent: `expect($value)` wraps the value,
 * then a matcher method asserts against it. Testo's {@see \Testo\Assert} facade is
 * actual-first (`Assert::same($actual, $expected)`), so the value passed to `expect()`
 * becomes the FIRST argument of the emitted `Assert::*` call and the matcher's own
 * argument (if any) follows.
 *
 * Mapped matchers:
 *   - toBe($e)            => Assert::same($value, $e)
 *   - toEqual($e)         => Assert::equals($value, $e)
 *   - toBeTrue()          => Assert::true($value)
 *   - toBeFalse()         => Assert::false($value)
 *   - toBeNull()          => Assert::null($value)
 *   - toBeInstanceOf($c)  => Assert::instanceOf($value, $c)
 *   - toContain($x)       => Assert::contains($value, $x)
 *   - toHaveCount($n)     => Assert::count($value, $n)
 *
 * ONLY the simple single-expectation form is converted. Anything that does not fit
 * is deliberately left untouched so it stays visibly unconverted rather than being
 * silently mistranslated:
 *   - chained expectations (`expect($v)->toBe(1)->toBe(2)`): the outer matcher's
 *     `var` is another MethodCall, not the `expect()` FuncCall, so it is skipped;
 *   - negated expectations (`expect($v)->not->toBe(...)`): the matcher's `var` is a
 *     `->not` PropertyFetch, not the `expect()` FuncCall, so it is skipped;
 *   - unmapped matchers (`toBeGreaterThan`, custom matchers): not in the map, skipped.
 */
#[TestRectorFixtures('ExpectToAssertRector')]
final class ExpectToAssertRector extends AbstractRector
{
    /**
     * Pest matcher => [Testo Assert method, appends the matcher argument?].
     *
     * When the second element is `true`, the matcher's first argument is appended
     * after `$value`; when `false`, the matcher takes no argument and only `$value`
     * is passed.
     *
     * @var array<non-empty-string, array{non-empty-string, bool}>
     */
    private const MAP = [
        'toBe' => ['same', true],
        'toEqual' => ['equals', true],
        'toBeTrue' => ['true', false],
        'toBeFalse' => ['false', false],
        'toBeNull' => ['null', false],
        'toBeInstanceOf' => ['instanceOf', true],
        'toContain' => ['contains', true],
        'toHaveCount' => ['count', true],
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert a single Pest `expect($value)->toX(...)` expectation into the matching actual-first `\Testo\Assert::*` static call',
            [
                new CodeSample(
                    <<<'PHP'
                        expect($result)->toBe(3);
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
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // The matcher's receiver must be the `expect(...)` free-function call itself.
        // This naturally excludes chained (`->toBe()->toBe()`) and negated
        // (`->not->...`) forms, whose `var` is a MethodCall / PropertyFetch instead.
        $expect = $node->var;
        if (!$expect instanceof FuncCall || !$this->isName($expect->name, 'expect')) {
            return null;
        }

        // `expect()` must wrap exactly one positional value.
        if (\count($expect->args) !== 1 || !$expect->args[0] instanceof Arg) {
            return null;
        }

        $matcher = $this->getName($node->name);
        if ($matcher === null || !isset(self::MAP[$matcher])) {
            return null;
        }

        [$assertMethod, $takesArgument] = self::MAP[$matcher];

        $value = $expect->args[0];
        $args = [$value];

        if ($takesArgument) {
            // Mapped value-taking matchers expect exactly one argument; bail otherwise.
            if (\count($node->args) !== 1 || !$node->args[0] instanceof Arg) {
                return null;
            }

            $args[] = $node->args[0];
        } elseif ($node->args !== []) {
            // Argument-less matchers (toBeTrue, ...) must not carry arguments.
            return null;
        }

        return new StaticCall(
            new FullyQualified('Testo\\Assert'),
            new Identifier($assertMethod),
            $args,
        );
    }
}
