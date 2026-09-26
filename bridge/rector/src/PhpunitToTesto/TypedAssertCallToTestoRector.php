<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\PhpunitAssertionCall;
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
 *   - $this->assertStringStartsWith($p, $s)  → \Testo\Assert::string($s)->startsWith($p)
 *   - $this->assertStringEndsWith($p, $s)    → \Testo\Assert::string($s)->endsWith($p)
 *   - $this->assertStringContainsString($n, $s)    → \Testo\Assert::string($s)->contains($n)
 *   - $this->assertStringNotContainsString($n, $s) → \Testo\Assert::string($s)->notContains($n)
 *   - $this->assertNotContains($n, $h)       → \Testo\Assert::iterable($h)->notContains($n)
 *   - $this->assertObjectHasProperty($p, $o) → \Testo\Assert::object($o)->hasProperty($p)
 *   - $this->assertIsList($a)                → \Testo\Assert::array($a)->isList()
 *   - $this->assertIsString($x)              → \Testo\Assert::string($x), and so on for the heads
 *
 * Both sides treat an empty substring as contained, compare iterable elements with `===` (as the flat
 * `assertContains` conversion does) and check a property with private and dynamic ones included, so
 * these chains keep PHPUnit's verdict.
 *
 * A type head takes no message, so `assertIsString($x, $message)` becomes
 * `\Testo\Assert::true(\is_string($x), $message)`. The checks with no matcher (`assertIsBool`,
 * `assertIsCallable`, `assertIsNot*`, `assertFileExists`, `assertDirectoryExists`, …) become
 * `Assert::true|false()` over the predicate PHPUnit runs itself.
 *
 * `assertEmpty`/`assertNotEmpty` map to the flat `\Testo\Assert::blank()`/`notBlank()` — but only when
 * the subject's inferred type is an array. Testo's `blank()` treats `false`/`0`/`'0'` as valid
 * (non-blank) data, so converting a call whose subject could be one of those would change meaning;
 * an array can never be `false`/`0`/`'0'`, so there the two notions coincide and the rewrite is
 * faithful. A subject that cannot be an object gets `Assert::true(empty($x))` instead, which is
 * PHPUnit's own check; an object or unknown subject is left untouched, since PHPUnit counts a
 * `Countable` — see TODO.md.
 *
 * Message residual: the numeric matchers and `sameElementsAs()`/`blank()`/`notBlank()` all keep a
 * trailing `$message`, so it is preserved there. The array-key matchers (`hasKeys()`/
 * `doesNotHaveKeys()`) are variadic with no `$message` parameter, so a PHPUnit message on
 * `assertArrayHasKey`/`assertArrayNotHasKey` is dropped (documented in TODO.md; mirrors the reverse
 * direction, which emits keyed assertions without a message too).
 *
 * Recognises the same call spellings as {@see AssertCallToTestoRector}, `PHPUnit\Framework\assert*()`
 * functions included, and likewise only inside a class.
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

    /**
     * Type assertions → the `Assert::<head>($subject)` type check of the same name.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const TYPE_HEAD = [
        'assertIsString' => 'string',
        'assertIsInt' => 'int',
        'assertIsFloat' => 'float',
        'assertIsNumeric' => 'numeric',
        'assertIsArray' => 'array',
        'assertIsIterable' => 'iterable',
        'assertIsObject' => 'object',
    ];

    /**
     * Assertions with no Testo matcher → `Assert::true|false(\function($subject))`, the check PHPUnit
     * runs itself. `$message` is preserved.
     *
     * @var array<non-empty-string, array{'true'|'false', non-empty-string}>
     */
    private const PREDICATE = [
        'assertIsBool' => ['true', 'is_bool'],
        'assertIsCallable' => ['true', 'is_callable'],
        'assertIsScalar' => ['true', 'is_scalar'],
        'assertIsNotString' => ['false', 'is_string'],
        'assertIsNotInt' => ['false', 'is_int'],
        'assertIsNotFloat' => ['false', 'is_float'],
        'assertIsNotNumeric' => ['false', 'is_numeric'],
        'assertIsNotArray' => ['false', 'is_array'],
        'assertIsNotIterable' => ['false', 'is_iterable'],
        'assertIsNotObject' => ['false', 'is_object'],
        'assertIsNotBool' => ['false', 'is_bool'],
        'assertIsNotCallable' => ['false', 'is_callable'],
        'assertIsNotScalar' => ['false', 'is_scalar'],
        'assertFileExists' => ['true', 'file_exists'],
        'assertFileDoesNotExist' => ['false', 'file_exists'],
        'assertDirectoryExists' => ['true', 'is_dir'],
        'assertDirectoryDoesNotExist' => ['false', 'is_dir'],
        'assertIsReadable' => ['true', 'is_readable'],
        'assertIsNotReadable' => ['false', 'is_readable'],
        'assertIsWritable' => ['true', 'is_writable'],
        'assertIsNotWritable' => ['false', 'is_writable'],
    ];

    public function __construct(
        private readonly PhpunitAssertionCall $assertionCall,
    ) {}

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

        return match (true) {
            isset(self::NUMERIC[$method]) => $this->typedChain('numeric', self::NUMERIC[$method], $node->args, keepMessage: true),
            isset(self::ARRAY_KEY[$method]) => $this->typedChain('array', self::ARRAY_KEY[$method], $node->args, keepMessage: false),
            $method === 'assertEqualsCanonicalizing' => $this->typedChain('array', 'sameElementsAs', $node->args, keepMessage: true),
            $method === 'assertStringStartsWith' => $this->typedChain('string', 'startsWith', $node->args, keepMessage: true),
            $method === 'assertStringEndsWith' => $this->typedChain('string', 'endsWith', $node->args, keepMessage: true),
            $method === 'assertStringContainsString' => $this->typedChain('string', 'contains', $node->args, keepMessage: true),
            $method === 'assertStringNotContainsString' => $this->typedChain('string', 'notContains', $node->args, keepMessage: true),
            $method === 'assertNotContains' => $this->typedChain('iterable', 'notContains', $node->args, keepMessage: true),
            $method === 'assertObjectHasProperty' => $this->typedChain('object', 'hasProperty', $node->args, keepMessage: true),
            $method === 'assertIsList' => $this->subjectChain('array', 'isList', [], $node->args),
            $method === 'assertEmpty' => $this->emptiness('blank', 'true', $node->args),
            $method === 'assertNotEmpty' => $this->emptiness('notBlank', 'false', $node->args),
            isset(self::TYPE_HEAD[$method]) => $this->typeCheck($method, $node->args),
            isset(self::PREDICATE[$method]) => $this->predicate(...self::PREDICATE[$method], args: $node->args),
            default => null,
        };
    }

    /**
     * `assertIsString($subject)` → `Assert::string($subject)`. A type head takes no message, so a call
     * that carries one falls back to the predicate form, which keeps it.
     *
     * @param non-empty-string $method
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function typeCheck(string $method, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        if (isset($args[1])) {
            return $this->predicate('true', 'is_' . self::TYPE_HEAD[$method], $args);
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier(self::TYPE_HEAD[$method]), [$subject]);
    }

    /**
     * `assertX($subject[, $message])` → `Assert::true|false(\predicate($subject)[, $message])`.
     *
     * @param 'true'|'false' $assert
     * @param non-empty-string $function
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function predicate(string $assert, string $function, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        $callArgs = [new Arg(new FuncCall(new FullyQualified($function), [$subject]))];
        if (($args[1] ?? null) instanceof Arg) {
            $callArgs[] = $args[1];
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($assert), $callArgs);
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
     * `assertX($subject[, $message])` → `Assert::<head>($subject)-><matcher>(...$matcherArgs[, $message])`.
     *
     * @param non-empty-string $head The Testo `Assert::<head>()` type check.
     * @param non-empty-string $matcher The chained matcher method.
     * @param list<Arg> $matcherArgs The matcher arguments that precede the message.
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function subjectChain(string $head, string $matcher, array $matcherArgs, array $args): ?MethodCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        if (($args[1] ?? null) instanceof Arg) {
            $matcherArgs[] = $args[1];
        }

        return new MethodCall(
            new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($head), [$subject]),
            new Identifier($matcher),
            $matcherArgs,
        );
    }

    /**
     * `assertEmpty($subject[, $message])` → `Assert::blank($subject[, $message])` (and `notBlank` for
     * `assertNotEmpty`) for an array subject, where `blank()` and PHP's `empty()` coincide. A subject
     * that cannot be an object becomes `Assert::true(empty($subject))` (`false` for `assertNotEmpty`),
     * which is exactly PHPUnit's check; an object may be `Countable`, which PHPUnit counts instead.
     *
     * @param non-empty-string $testoMethod
     * @param 'true'|'false' $emptyAssert
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function emptiness(string $testoMethod, string $emptyAssert, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        $type = $this->getType($subject->value);
        if (!$type->isArray()->yes()) {
            if (!$type->isObject()->no()) {
                return null;
            }

            $testoMethod = $emptyAssert;
            $subject = new Arg(new Empty_($subject->value));
        }

        $callArgs = [$subject];
        if (($args[1] ?? null) instanceof Arg) {
            $callArgs[] = $args[1];
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($testoMethod), $callArgs);
    }
}
