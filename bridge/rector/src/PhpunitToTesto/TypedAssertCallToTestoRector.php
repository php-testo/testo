<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Rewrites the PHPUnit assertions that map onto a Testo **typed head + matcher** chain rather than a
 * flat facade call — the shape {@see AssertCallToTestoRector} deliberately does not handle.
 *
 * The subject moves from an argument position to the head argument (still evaluated exactly once,
 * so no hoisting is needed) and the remaining argument becomes the matcher argument:
 *
 *   - $this->assertGreaterThan($e, $a)     → \Testo\Assert::numeric($a)->greaterThan($e)
 *   - $this->assertGreaterThanOrEqual(...) → …->greaterThanOrEqual(…)
 *   - $this->assertLessThan(...)           → …->lessThan(…)
 *   - $this->assertLessThanOrEqual(...)    → …->lessThanOrEqual(…)
 *   - $this->assertArrayHasKey($k, $a)     → \Testo\Assert::array($a)->hasKeys($k)
 *   - $this->assertArrayNotHasKey($k, $a)  → \Testo\Assert::array($a)->doesNotHaveKeys($k)
 *   - $this->assertEqualsCanonicalizing($e, $a) → \Testo\Assert::array($a)->sameElementsAs($e)
 *
 * `assertEmpty`/`assertNotEmpty` map to the flat `\Testo\Assert::blank()`/`notBlank()` — but only when
 * the subject's inferred type is an array. Testo's `blank()` treats `false`/`0`/`'0'` as valid
 * (non-blank) data, so converting a call whose subject could be one of those would change meaning;
 * an array can never be `false`/`0`/`'0'`, so there the two notions coincide and the rewrite is
 * faithful. A non-array (or unknown) subject is left untouched — see TODO.md.
 *
 * Message residual: the numeric matchers and `sameElementsAs()`/`blank()`/`notBlank()` all keep a
 * trailing `$message`, so it is preserved there. The array-key matchers (`hasKeys()`/
 * `doesNotHaveKeys()`) are variadic with no `$message` parameter, so a PHPUnit message on
 * `assertArrayHasKey`/`assertArrayNotHasKey` is dropped (documented in TODO.md; mirrors the reverse
 * direction, which emits keyed assertions without a message too).
 *
 * Only rewrites a call inside a class (a test method or a `static` data provider), mirroring
 * {@see AssertCallToTestoRector}; a matching call in a free function or at namespace level is left
 * untouched.
 */
#[TestRectorFixtures('TypedAssertCallToTestoRector')]
final class TypedAssertCallToTestoRector extends AbstractRector
{
    /**
     * Comparison assertions → the `Assert::numeric($subject)->…` matcher. `$message` is preserved.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const NUMERIC = [
        'assertGreaterThan' => 'greaterThan',
        'assertGreaterThanOrEqual' => 'greaterThanOrEqual',
        'assertLessThan' => 'lessThan',
        'assertLessThanOrEqual' => 'lessThanOrEqual',
    ];

    /**
     * Array-key assertions → the `Assert::array($subject)->…` matcher. `$message` is dropped
     * (the matchers are variadic with no message parameter).
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const ARRAY_KEY = [
        'assertArrayHasKey' => 'hasKeys',
        'assertArrayNotHasKey' => 'doesNotHaveKeys',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Convert PHPUnit comparison / array-key / canonicalizing / emptiness assertions into Testo typed Assert chains',
            [
                new CodeSample(
                    <<<'PHP'
                        $this->assertGreaterThan(0, $n);
                        $this->assertArrayHasKey('id', $row);
                        PHP,
                    <<<'PHP'
                        \Testo\Assert::numeric($n)->greaterThan(0);
                        \Testo\Assert::array($row)->hasKeys('id');
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
        } elseif (!$this->isName($node->class, 'self') && !$this->isName($node->class, 'static')) {
            return null;
        }

        $method = $this->getName($node->name);
        if ($method === null) {
            return null;
        }

        # An assertion belongs to a test method (or a static data provider); a stray call in a free
        # function or at namespace level is left as-is — same gate as AssertCallToTestoRector.
        if (!$this->isInClassScope($node)) {
            return null;
        }

        return match (true) {
            isset(self::NUMERIC[$method]) => $this->typedChain('numeric', self::NUMERIC[$method], $node->args, keepMessage: true),
            isset(self::ARRAY_KEY[$method]) => $this->typedChain('array', self::ARRAY_KEY[$method], $node->args, keepMessage: false),
            $method === 'assertEqualsCanonicalizing' => $this->typedChain('array', 'sameElementsAs', $node->args, keepMessage: true),
            $method === 'assertEmpty' => $this->emptiness('blank', $node->args),
            $method === 'assertNotEmpty' => $this->emptiness('notBlank', $node->args),
            default => null,
        };
    }

    /**
     * `assert*($needle, $subject[, $message])` → `Assert::<head>($subject)-><matcher>($needle[, $message])`.
     *
     * @param non-empty-string $head The Testo `Assert::<head>()` type check.
     * @param non-empty-string $matcher The chained matcher method.
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     * @param bool $keepMessage Whether the matcher accepts (and should keep) the trailing `$message`.
     */
    private function typedChain(string $head, string $matcher, array $args, bool $keepMessage): ?MethodCall
    {
        $needle = $args[0] ?? null;
        $subject = $args[1] ?? null;
        if (!$needle instanceof Arg || !$subject instanceof Arg) {
            return null;
        }

        $matcherArgs = [$needle];
        if ($keepMessage && ($args[2] ?? null) instanceof Arg) {
            $matcherArgs[] = $args[2];
        }

        return new MethodCall(
            new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($head), [$subject]),
            new Identifier($matcher),
            $matcherArgs,
        );
    }

    /**
     * `assertEmpty($subject[, $message])` → `Assert::blank($subject[, $message])` (and `notBlank` for
     * `assertNotEmpty`), but only for an array subject where `blank()` and PHP's `empty()` coincide.
     *
     * @param non-empty-string $testoMethod
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function emptiness(string $testoMethod, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg || !$this->getType($subject->value)->isArray()->yes()) {
            return null;
        }

        $callArgs = [$subject];
        if (($args[1] ?? null) instanceof Arg) {
            $callArgs[] = $args[1];
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($testoMethod), $callArgs);
    }

    /**
     * Whether $node sits inside a class. Outside one the assertion is left untouched.
     */
    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
