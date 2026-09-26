<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\String_;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\UnionType;
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
 *   - $this->assertStringStartsNotWith($p, $s) → \Testo\Assert::string($s)->notStartsWith($p), likewise
 *     `assertStringEndsNotWith` → `…->notEndsWith($p)`
 *   - $this->assertStringContainsString($n, $s)    → \Testo\Assert::string($s)->contains($n)
 *   - $this->assertStringNotContainsString($n, $s) → \Testo\Assert::string($s)->notContains($n)
 *   - $this->assertStringContainsStringIgnoringCase($n, $s) → \Testo\Assert::string($s)->ignoringCase()->contains($n),
 *     likewise `assertStringNotContainsStringIgnoringCase` → `…->ignoringCase()->notContains($n)` and
 *     `assertStringContainsStringIgnoringLineEndings` → `…->ignoringLineEndings()->contains($n)`
 *   - $this->assertStringEqualsStringIgnoringLineEndings($e, $s) → \Testo\Assert::string($s)->ignoringLineEndings()->same($e)
 *   - $this->assertStringEqualsStringIgnoringWhitespace($e, $s) →
 *     \Testo\Assert::string($s)->ignoringWhitespace(lineBreaks: true)->same($e), and `notSame()` for the negation
 *   - $this->assertEqualsIgnoringCase($e, $a) → \Testo\Assert::string($a)->ignoringCase()->same($e), and
 *     `notSame()` for `assertNotEqualsIgnoringCase`; only when both sides are strings
 *   - $this->assertMatchesRegularExpression($p, $s)      → \Testo\Assert::string($s)->matchesRegex($p),
 *     also the PHPUnit 9 `assertRegExp`
 *   - $this->assertDoesNotMatchRegularExpression($p, $s) → \Testo\Assert::string($s)->notMatchesRegex($p),
 *     also the PHPUnit 9 `assertNotRegExp`
 *   - $this->assertNotContains($n, $h)       → \Testo\Assert::iterable($h)->notContains($n)
 *   - $this->assertObjectHasProperty($p, $o) → \Testo\Assert::object($o)->hasProperty($p)
 *   - $this->assertIsList($a)                → \Testo\Assert::array($a)->isList()
 *   - $this->assertContainsOnlyInt($h)       → \Testo\Assert::iterable($h)->allOf('int'), likewise for
 *     `Array`/`Bool`/`Float`/`Null`/`String`
 *   - $this->assertContainsOnlyInstancesOf($c, $h) → \Testo\Assert::iterable($h)->allInstanceOf($c)
 *   - $this->assertSameSize($e, $a)          → \Testo\Assert::iterable($a)->sameSizeAs($e), when both
 *     sides are arrays or `Countable` iterables
 *   - $this->assertJson($s)                  → \Testo\Assert::json($s)
 *   - $this->assertIsString($x)              → \Testo\Assert::string($x), and so on for the heads
 *
 * Both sides treat an empty substring as contained, compare iterable elements with `===` (as the flat
 * `assertContains` conversion does) and check a property with private and dynamic ones included, so
 * these chains keep PHPUnit's verdict. An invalid pattern is an error on both sides, negated or not. *
 * A type head takes no message, so `assertIsString($x, $message)` becomes
 * `\Testo\Assert::true(\is_string($x), $message)`. The checks with no matcher (`assertIsScalar`,
 * `assertIsNot*`, `assertFileExists`, `assertDirectoryExists`, …) become
 * `Assert::true|false()` over the predicate PHPUnit runs itself. Where PHPUnit narrows that predicate,
 * the conversion follows it or stays out:
 *
 *   - assertFileIsNotReadable($f)   → Assert::true(\file_exists($f) && !\is_readable($f)), and so on for
 *     the `assertFileIs*`/`assertDirectoryIs*` permission checks; only for a side-effect-free path
 *   - assertFinite($x)              → Assert::true(\is_finite($x)), also `Infinite`/`Nan`; only for an
 *     `int|float` subject
 *   - assertIsResource($x)          → Assert::true(\str_starts_with(\gettype($x), 'resource')), since a
 *     closed resource counts; `assertIsClosedResource` compares `\gettype()` with 'resource (closed)'
 *   - assertNotInstanceOf(Foo::class, $x) → Assert::false($x instanceof Foo); only for an existing class
 *     or interface
 *   - assertObjectNotHasProperty($p, $o)  → Assert::false(\property_exists($o, $p)); only for an object
 *     subject
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
 * direction, which emits keyed assertions without a message too). `Assert::json()` takes no message
 * either, so one on `assertJson` is dropped as well.
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
     * String assertions with a comparison mode → `Assert::string($subject)-><modifier>()-><matcher>()`.
     * `$message` is preserved. PHPUnit's whitespace mode turns every run of whitespace, line breaks
     * included, into one space and trims, which is `ignoringWhitespace(lineBreaks: true)`.
     *
     * @var array<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    private const STRING_MODIFIED = [
        'assertStringContainsStringIgnoringCase' => ['ignoringCase', 'contains'],
        'assertStringNotContainsStringIgnoringCase' => ['ignoringCase', 'notContains'],
        'assertStringContainsStringIgnoringLineEndings' => ['ignoringLineEndings', 'contains'],
        'assertStringEqualsStringIgnoringLineEndings' => ['ignoringLineEndings', 'same'],
        'assertStringEqualsStringIgnoringWhitespace' => ['ignoringWhitespace', 'same'],
        'assertStringNotEqualsStringIgnoringWhitespace' => ['ignoringWhitespace', 'notSame'],
    ];

    /**
     * Case-insensitive equality → `Assert::string($actual)->ignoringCase()->same|notSame($expected)`,
     * only when both sides are strings: PHPUnit also compares other scalars and arrays, recursively.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const EQUALS_IGNORING_CASE = [
        'assertEqualsIgnoringCase' => 'same',
        'assertNotEqualsIgnoringCase' => 'notSame',
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
        'assertIsBool' => 'bool',
        'assertIsCallable' => 'callable',
    ];

    /**
     * Assertions with no Testo matcher → `Assert::true|false(\function($subject))`, the check PHPUnit
     * runs itself. `$message` is preserved.
     *
     * @var array<non-empty-string, array{'true'|'false', non-empty-string}>
     */
    private const PREDICATE = [
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
        # `is_readable()`/`is_writable()` fail for a missing path, which covers PHPUnit's existence check.
        'assertFileIsReadable' => ['true', 'is_readable'],
        'assertFileIsWritable' => ['true', 'is_writable'],
    ];

    /**
     * Path assertions PHPUnit runs as an existence check plus a permission check →
     * `Assert::true(\exists($path) && [!]\permission($path))`. The path is read twice, so only a
     * side-effect-free path expression converts. `$message` is preserved.
     *
     * @var array<non-empty-string, array{non-empty-string, non-empty-string, bool}>
     */
    private const PATH_PERMISSION = [
        'assertFileIsNotReadable' => ['file_exists', 'is_readable', false],
        'assertFileIsNotWritable' => ['file_exists', 'is_writable', false],
        'assertDirectoryIsReadable' => ['is_dir', 'is_readable', true],
        'assertDirectoryIsNotReadable' => ['is_dir', 'is_readable', false],
        'assertDirectoryIsWritable' => ['is_dir', 'is_writable', true],
        'assertDirectoryIsNotWritable' => ['is_dir', 'is_writable', false],
    ];

    /**
     * Math assertions → `Assert::true(\function($subject))`, only for an `int|float` subject: PHPUnit
     * fails any other type, while the function would coerce a numeric string or throw.
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const MATH = [
        'assertFinite' => 'is_finite',
        'assertInfinite' => 'is_infinite',
        'assertNan' => 'is_nan',
    ];

    /**
     * Resource assertions → a check of `\gettype($subject)`, since PHPUnit counts a closed resource as a
     * resource and `is_resource()` does not: `Assert::true|false(\str_starts_with(\gettype($x), 'resource'))`
     * for any resource, `Assert::same|notSame(\gettype($x), 'resource (closed)')` for a closed one.
     *
     * @var array<non-empty-string, 'true'|'false'|'same'|'notSame'>
     */
    private const RESOURCE = [
        'assertIsResource' => 'true',
        'assertIsNotResource' => 'false',
        'assertIsClosedResource' => 'same',
        'assertIsNotClosedResource' => 'notSame',
    ];

    /**
     * Native-type `assertContainsOnly*` → the `allOf()` type name, limited to the types whose
     * `get_debug_type()` spelling is the type itself (an object reports its class, a resource its kind).
     *
     * @var array<non-empty-string, non-empty-string>
     */
    private const CONTAINS_ONLY = [
        'assertContainsOnlyArray' => 'array',
        'assertContainsOnlyBool' => 'bool',
        'assertContainsOnlyFloat' => 'float',
        'assertContainsOnlyInt' => 'int',
        'assertContainsOnlyNull' => 'null',
        'assertContainsOnlyString' => 'string',
    ];

    public function __construct(
        private readonly PhpunitAssertionCall $assertionCall,
        private readonly ReflectionProvider $reflectionProvider,
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
            $method === 'assertStringStartsNotWith' => $this->typedChain('string', 'notStartsWith', $node->args, keepMessage: true),
            $method === 'assertStringEndsNotWith' => $this->typedChain('string', 'notEndsWith', $node->args, keepMessage: true),
            $method === 'assertStringContainsString' => $this->typedChain('string', 'contains', $node->args, keepMessage: true),
            $method === 'assertStringNotContainsString' => $this->typedChain('string', 'notContains', $node->args, keepMessage: true),
            isset(self::STRING_MODIFIED[$method]) => $this->typedChain(
                'string',
                self::STRING_MODIFIED[$method][1],
                $node->args,
                keepMessage: true,
                modifier: self::STRING_MODIFIED[$method][0],
            ),
            isset(self::EQUALS_IGNORING_CASE[$method]) && $this->areStrings($node->args) => $this->typedChain(
                'string',
                self::EQUALS_IGNORING_CASE[$method],
                $node->args,
                keepMessage: true,
                modifier: 'ignoringCase',
            ),
            $method === 'assertMatchesRegularExpression', $method === 'assertRegExp'
                => $this->typedChain('string', 'matchesRegex', $node->args, keepMessage: true),
            $method === 'assertDoesNotMatchRegularExpression', $method === 'assertNotRegExp'
                => $this->typedChain('string', 'notMatchesRegex', $node->args, keepMessage: true),
            $method === 'assertNotContains' => $this->typedChain('iterable', 'notContains', $node->args, keepMessage: true),
            $method === 'assertObjectHasProperty' => $this->typedChain('object', 'hasProperty', $node->args, keepMessage: true),
            $method === 'assertIsList' => $this->subjectChain('array', 'isList', [], $node->args),
            isset(self::CONTAINS_ONLY[$method]) => $this->subjectChain('iterable', 'allOf', [new Arg(new String_(self::CONTAINS_ONLY[$method]))], $node->args),
            $method === 'assertContainsOnlyInstancesOf' => $this->typedChain('iterable', 'allInstanceOf', $node->args, keepMessage: true),
            $method === 'assertSameSize' => $this->sameSize($node->args),
            $method === 'assertJson' => $this->json($node->args),
            $method === 'assertEmpty' => $this->emptiness('blank', 'true', $node->args),
            $method === 'assertNotEmpty' => $this->emptiness('notBlank', 'false', $node->args),
            isset(self::TYPE_HEAD[$method]) => $this->typeCheck($method, $node->args),
            isset(self::PREDICATE[$method]) => $this->predicate(...self::PREDICATE[$method], args: $node->args),
            isset(self::PATH_PERMISSION[$method]) => $this->pathPermission(...self::PATH_PERMISSION[$method], args: $node->args),
            isset(self::MATH[$method]) => $this->math(self::MATH[$method], $node->args),
            isset(self::RESOURCE[$method]) => $this->resource(self::RESOURCE[$method], $node->args),
            $method === 'assertNotInstanceOf' => $this->notInstanceOf($node->args),
            $method === 'assertObjectNotHasProperty' => $this->objectNotHasProperty($node->args),
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
     * `assertX($path[, $message])` → `Assert::true(\exists($path) && [!]\permission($path)[, $message])`.
     *
     * @param non-empty-string $exists
     * @param non-empty-string $permission
     * @param bool $granted Whether the permission must be present rather than absent.
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function pathPermission(string $exists, string $permission, bool $granted, array $args): ?StaticCall
    {
        $path = $args[0] ?? null;
        if (!$path instanceof Arg || !$this->isSideEffectFree($path->value)) {
            return null;
        }

        $check = new FuncCall(new FullyQualified($permission), [new Arg($path->value)]);
        $granted or $check = new BooleanNot($check);
        $exists = new FuncCall(new FullyQualified($exists), [new Arg($path->value)]);

        return $this->assertCall('true', [new Arg(new BooleanAnd($exists, $check))], $args[1] ?? null);
    }

    /**
     * `assertFinite($subject[, $message])` → `Assert::true(\is_finite($subject)[, $message])` for an
     * `int|float` subject.
     *
     * @param non-empty-string $function
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function math(string $function, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        $number = new UnionType([new IntegerType(), new FloatType()]);
        if (!$number->isSuperTypeOf($this->getType($subject->value))->yes()) {
            return null;
        }

        return $this->predicate('true', $function, $args);
    }

    /**
     * `assertIsResource($subject[, $message])` and its siblings → a check of `\gettype($subject)`.
     *
     * @param 'true'|'false'|'same'|'notSame' $assert
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function resource(string $assert, array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        $gettype = new FuncCall(new FullyQualified('gettype'), [$subject]);
        $checkArgs = $assert === 'same' || $assert === 'notSame'
            ? [new Arg($gettype), new Arg(new String_('resource (closed)'))]
            : [new Arg(new FuncCall(new FullyQualified('str_starts_with'), [new Arg($gettype), new Arg(new String_('resource'))]))];

        return $this->assertCall($assert, $checkArgs, $args[1] ?? null);
    }

    /**
     * `assertNotInstanceOf($class, $subject[, $message])` → `Assert::false($subject instanceof Foo[, $message])`
     * for a class or interface that exists, since PHPUnit throws for an unknown one.
     *
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function notInstanceOf(array $args): ?StaticCall
    {
        $class = $args[0] ?? null;
        $subject = $args[1] ?? null;
        if (!$class instanceof Arg || !$subject instanceof Arg) {
            return null;
        }

        $reflection = $this->classReflection($class->value);
        if ($reflection === null || $reflection->isTrait()) {
            return null;
        }

        $name = $class->value instanceof ClassConstFetch && $class->value->class instanceof Name
            ? $class->value->class
            : new FullyQualified($reflection->getName());

        return $this->assertCall('false', [new Arg(new Instanceof_($subject->value, $name))], $args[2] ?? null);
    }

    /**
     * `assertObjectNotHasProperty($name, $object[, $message])` →
     * `Assert::false(\property_exists($object, $name)[, $message])` for an object subject: a class-name
     * string would reach `property_exists()`, which accepts one, where PHPUnit's `object` parameter throws.
     *
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function objectNotHasProperty(array $args): ?StaticCall
    {
        $name = $args[0] ?? null;
        $object = $args[1] ?? null;
        if (!$name instanceof Arg || !$object instanceof Arg || !$this->getType($object->value)->isObject()->yes()) {
            return null;
        }

        return $this->assertCall(
            'false',
            [new Arg(new FuncCall(new FullyQualified('property_exists'), [$object, $name]))],
            $args[2] ?? null,
        );
    }

    /**
     * `Assert::<method>(...$checkArgs[, $message])`.
     *
     * @param non-empty-string $method
     * @param list<Arg> $checkArgs
     */
    private function assertCall(string $method, array $checkArgs, Node\Arg|Node\VariadicPlaceholder|null $message): StaticCall
    {
        $message instanceof Arg and $checkArgs[] = $message;

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($method), $checkArgs);
    }

    /**
     * Whether evaluating the expression twice reads the same value with no side effect: a variable, a
     * literal, a constant, or a concatenation of those (`__DIR__ . '/file'`).
     */
    private function isSideEffectFree(Expr $expr): bool
    {
        return match (true) {
            $expr instanceof Variable, $expr instanceof String_, $expr instanceof MagicConst,
            $expr instanceof ConstFetch => true,
            $expr instanceof ClassConstFetch => $expr->class instanceof Name,
            $expr instanceof Concat => $this->isSideEffectFree($expr->left) && $this->isSideEffectFree($expr->right),
            default => false,
        };
    }

    /**
     * `assert*($needle, $subject[, $message])` → `Assert::<head>($subject)-><matcher>($needle[, $message])`.
     *
     * @param non-empty-string $head The Testo `Assert::<head>()` type check.
     * @param non-empty-string $matcher The chained matcher method.
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     * @param bool $keepMessage Whether the matcher accepts (and should keep) the trailing `$message`.
     * @param non-empty-string|null $modifier A comparison modifier chained between the head and the matcher.
     */
    private function typedChain(
        string $head,
        string $matcher,
        array $args,
        bool $keepMessage,
        ?string $modifier = null,
    ): ?MethodCall {
        $needle = $args[0] ?? null;
        $subject = $args[1] ?? null;
        if (!$needle instanceof Arg || !$subject instanceof Arg) {
            return null;
        }

        $matcherArgs = [$needle];
        if ($keepMessage && ($args[2] ?? null) instanceof Arg) {
            $matcherArgs[] = $args[2];
        }

        $chain = new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier($head), [$subject]);
        $modifier === null or $chain = new MethodCall(
            $chain,
            new Identifier($modifier),
            $modifier === 'ignoringWhitespace'
                ? [new Arg(new ConstFetch(new Name('true')), name: new Identifier('lineBreaks'))]
                : [],
        );

        return new MethodCall($chain, new Identifier($matcher), $matcherArgs);
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
     * Whether the first two arguments are both known to be strings.
     *
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function areStrings(array $args): bool
    {
        $expected = $args[0] ?? null;
        $actual = $args[1] ?? null;

        return $expected instanceof Arg && $actual instanceof Arg
            && $this->getType($expected->value)->isString()->yes()
            && $this->getType($actual->value)->isString()->yes();
    }

    /**
     * `assertSameSize($e, $a[, $message])` → `Assert::iterable($a)->sameSizeAs($e[, $message])` when both
     * sides are arrays or `Countable` iterables. Both frameworks then read `count()`; a bare `Traversable`
     * is left alone, since PHPUnit rejects one that yields a `Generator` while Testo iterates it.
     *
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function sameSize(array $args): ?MethodCall
    {
        $expected = $args[0] ?? null;
        $actual = $args[1] ?? null;
        if (!$expected instanceof Arg || !$actual instanceof Arg) {
            return null;
        }

        if (!$this->isCountableIterable($expected->value) || !$this->isCountableIterable($actual->value)) {
            return null;
        }

        return $this->typedChain('iterable', 'sameSizeAs', $args, keepMessage: true);
    }

    /**
     * `assertJson($s[, $message])` → `Assert::json($s)`. Both reject an empty string and anything
     * `json_decode()` cannot parse at the default depth. The head takes no message, and the predicate
     * that could carry one, `json_validate()`, needs PHP 8.3, so the message is dropped.
     *
     * @param array<int, Node\Arg|Node\VariadicPlaceholder> $args
     */
    private function json(array $args): ?StaticCall
    {
        $subject = $args[0] ?? null;
        if (!$subject instanceof Arg) {
            return null;
        }

        return new StaticCall(new FullyQualified('Testo\\Assert'), new Identifier('json'), [$subject]);
    }

    private function isCountableIterable(Expr $expr): bool
    {
        $type = $this->getType($expr);

        return $type->isArray()->yes()
            || ($type->isIterable()->yes() && (new ObjectType(\Countable::class))->isSuperTypeOf($type)->yes());
    }

    /**
     * The existing class a `Foo::class` constant or a `'Foo'` string names, or null when it is dynamic,
     * relative (`self`, `static`, `parent`) or unknown.
     */
    private function classReflection(Expr $expr): ?ClassReflection
    {
        $name = match (true) {
            $expr instanceof ClassConstFetch && $expr->class instanceof Name && $this->isName($expr->name, 'class')
                && !$expr->class->isSpecialClassName() => $this->getName($expr->class),
            $expr instanceof String_ => \ltrim($expr->value, '\\'),
            default => null,
        };

        return $name === null || $name === '' || !$this->reflectionProvider->hasClass($name)
            ? null
            : $this->reflectionProvider->getClass($name);
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
