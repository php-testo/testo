<?php

declare(strict_types=1);

namespace Testo\PhpUnitBuild\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Build-only local rule: turn each individual test method that cannot be faithfully run under
 * PHPUnit into a skipped test, instead of discarding the whole class. Every original test stays
 * visible (its name, count and `#[Test]` marker are kept) and reports as skipped, so a class that
 * mixes convertible and non-convertible tests keeps the convertible ones live.
 *
 * A test method is skipped when:
 *   - its class lives in a runtime-bound namespace ({@see self::RUNTIME_NAMESPACES}) — the Facade
 *     tests need an active container — or is an Acceptance test that drives an external process by a
 *     path absent from the mirror;
 *   - it carries a composite data source ({@see self::COMPOSITE_DATA}) with no PHPUnit equivalent;
 *   - it declares an `Expect::exception()` expectation with a modifier that has no PHPUnit form
 *     (the substring `withMessageContaining`, `fromMethod`, …) — the chain stays as a `Testo\Expect::`
 *     call the runtime cannot satisfy under PHPUnit;
 *   - it is individually listed as unconvertible ({@see self::SKIP_METHODS}) — e.g. a Tokenizer test
 *     whose stub reprint fully-qualifies an unqualified external call.
 *
 * The Tokenizer self-tests used to be skipped wholesale (they assert on tokenized names/source); they
 * now run against their mirrored Stub data (see bin/build-phpunit.php), except the few in SKIP_METHODS.
 *
 * Tests that drive the Testo engine via `Testo\Testing\` (e.g. `TestRunner`) are deliberately NOT
 * skipped: PHPUnit — not the engine — discovers and runs them, so a mutation cannot break discovery
 * to fake a pass, and these tests are exactly what gives the pipeline its mutation coverage.
 *
 * Skipping PREPENDS a `$this->markTestSkipped(...)` and keeps the original body and attributes, so
 * the unconvertible test stays fully visible and simply reports as skipped instead of being blanked
 * out — the skip throws before the kept body runs. Parameters are dropped only when no data source
 * will feed them (so PHPUnit can still call the method: a Testo composite it ignores would otherwise
 * leave required parameters unfed and error before the skip); a method whose data source PHPUnit
 * honors keeps its parameters so the dataset arity still matches. Lifecycle hooks are left untouched
 * — they run their real body around a skipped test, which is harmless. Whole-file fatals (a custom
 * constructor, a name colliding with a `final` TestCase method) are handled earlier by
 * bin/build-phpunit.php, which cannot be fixed per method.
 */
final class SkipUnconvertibleTestMethodRector extends AbstractRector
{
    /** @var array<string, string> namespace prefix => reason */
    private const RUNTIME_NAMESPACES = [
        'Tests\\PhpUnit\\Facade' => 'depends on an active Testo\\Facade container, which only the FacadePlugin runtime provides',
    ];

    /**
     * Individually unconvertible tests, keyed by `ShortClassName::method`. The Tokenizer self-tests
     * run fine under PHPUnit once their Stub data is mirrored (see bin/build-phpunit.php) — except a
     * few whose stub contains an UNQUALIFIED external call in a free function: relocating the stub
     * reprints it and fully-qualifies that name (`SomeClass::x` → `\Tests\PhpUnit\…\SomeClass::x`),
     * so the tokenized class/source no longer matches what the test asserts. That is a mirror
     * reprint artifact, not a Testo behaviour, so those specific tests stay skipped.
     *
     * @var array<string, string>
     */
    private const SKIP_METHODS = [
        'TokenizedFileTest::getInvocationsDetectsExternalStaticCall' => 'reads a free-function stub whose unqualified external call the mirror reprint fully-qualifies, changing the tokenized class name',
        'TokenizedFileTest::getInvocationsSourceContainsFullCallExpression' => 'reads a free-function stub whose unqualified external call the mirror reprint fully-qualifies, changing the tokenized source',
    ];

    /** Composite data sources that have no PHPUnit equivalent. */
    private const COMPOSITE_DATA = [
        'Testo\\Data\\DataCross',
        'Testo\\Data\\DataZip',
        'Testo\\Data\\DataUnion',
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace a test method that cannot run under PHPUnit with a markTestSkipped() body, keeping it visible as a skipped test',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Test]
                        public function rejects(): never
                        {
                            \Testo\Expect::exception(X::class)->withMessageContaining('x');
                            $this->act();
                        }
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Test]
                        public function rejects(): never
                        {
                            $this->markTestSkipped('rejects() calls Testo\Expect with no PHPUnit form');
                            \Testo\Expect::exception(X::class)->withMessageContaining('x');
                            $this->act();
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    #[\Override]
    public function refactor(Node $node): ?Node
    {
        // A layout-bound namespace skips every test method of the class; otherwise each method is
        // judged on its own (composite data source, or an individually unconvertible test).
        $fqcn = $this->getName($node);
        $classReason = $this->namespaceReason($fqcn);

        $changed = false;
        foreach ($node->getMethods() as $method) {
            if (!$this->isRunnableTestMethod($method) || $this->isAlreadySkipped($method)) {
                continue;
            }

            $reason = $classReason ?? $this->methodReason($method, $fqcn);
            if ($reason === null) {
                continue;
            }

            $this->skip($method, $reason);
            $changed = true;
        }

        return $changed ? $node : null;
    }

    private function namespaceReason(?string $fqcn): ?string
    {
        if ($fqcn === null) {
            return null;
        }

        foreach (self::RUNTIME_NAMESPACES as $prefix => $why) {
            if (\str_starts_with($fqcn, $prefix . '\\')) {
                return $why;
            }
        }

        // Acceptance tests drive external processes (a console command, a `php script.php`) by path;
        // those paths do not exist in the relocated tests/PhpUnit mirror, so they cannot run here.
        if (\str_contains($fqcn, '\\Acceptance\\')) {
            return 'is an acceptance test that runs an external process by a path absent from the mirror';
        }

        return null;
    }

    /** A method PHPUnit will run as a test: public, non-static, concrete, carrying a Test attribute. */
    private function isRunnableTestMethod(ClassMethod $method): bool
    {
        if (!$method->isPublic() || $method->isStatic() || $method->isAbstract() || $method->stmts === null) {
            return false;
        }

        return $this->hasAttribute($method, ['PHPUnit\\Framework\\Attributes\\Test', 'Testo\\Test']);
    }

    private function methodReason(ClassMethod $method, ?string $fqcn): ?string
    {
        if ($this->hasAttribute($method, self::COMPOSITE_DATA)) {
            return 'uses a composite data source (Testo\\Data\\DataCross/DataZip/DataUnion) with no PHPUnit equivalent';
        }

        // An `Expect::exception()` chain carrying a modifier that ExpectExceptionToPhpUnitRector cannot
        // translate (only withMessage/withCode/withMessagePattern are mapped — the substring
        // withMessageContaining, fromMethod, withPrevious, … are not) is left as a `Testo\Expect::`
        // call, which needs the Testo runtime state absent under PHPUnit and would fatal with
        // StateNotFound. Skip such a method. (A chain with only mapped modifiers converts fine and is
        // left to run — detecting it by modifier name, not by "leftover Expect", is order-independent:
        // the skip rule may run before or after that conversion.)
        if ($this->hasUnconvertibleExpect($method)) {
            return 'declares an Expect::exception() expectation with a modifier that has no PHPUnit form (e.g. withMessageContaining)';
        }

        $shortClass = $fqcn === null ? '' : \substr($fqcn, (int) \strrpos($fqcn, '\\') + 1);

        return self::SKIP_METHODS[$shortClass . '::' . $this->getName($method)] ?? null;
    }

    /** Modifiers ExpectExceptionToPhpUnitRector can translate; any other on the chain aborts it. */
    private const MAPPED_EXPECT_MODIFIERS = ['withMessage', 'withCode', 'withMessagePattern'];

    /**
     * Whether the body holds a `Testo\Expect::exception(...)` chain with at least one modifier that has
     * no PHPUnit counterpart — the case ExpectExceptionToPhpUnitRector leaves unconverted. Each
     * modifier is a `MethodCall` whose `->var` chain bottoms out at the `exception()` head.
     */
    private function hasUnconvertibleExpect(ClassMethod $method): bool
    {
        foreach ((new \PhpParser\NodeFinder())->findInstanceOf($method->stmts ?? [], MethodCall::class) as $call) {
            $head = $call->var;
            while ($head instanceof MethodCall) {
                $head = $head->var;
            }
            if (!$head instanceof StaticCall || !$this->isName($head->class, 'Testo\\Expect') || !$this->isName($head->name, 'exception')) {
                continue;
            }

            $modifier = $this->getName($call->name);
            if ($modifier === null || !\in_array($modifier, self::MAPPED_EXPECT_MODIFIERS, true)) {
                return true;
            }
        }

        return false;
    }

    private function skip(ClassMethod $method, string $reason): void
    {
        $name = $this->getName($method) ?? 'test';

        $skip = new Expression(new MethodCall(
            new Variable('this'),
            new Identifier('markTestSkipped'),
            [new Arg(new String_("{$name}() {$reason}"))],
        ));

        // Prepend the skip, keeping the original body and attributes; the skip throws before the kept
        // body would run. Parameters are kept when a PHPUnit data source will feed them (so the
        // dataset arity still matches), and dropped otherwise so PHPUnit can still call the method — a
        // Testo composite source it ignores, or no source at all, would leave required parameters
        // unfed and error before the skip.
        $method->stmts = [$skip, ...($method->stmts ?? [])];
        if (!$this->hasHonoredDataSource($method)) {
            $method->params = [];
        }
    }

    /**
     * Whether a data source that PHPUnit will feed to the method's parameters is present — either the
     * PHPUnit attribute directly, or the Testo attribute the conversion turns into one (this rule may
     * run before or after that conversion). A Testo *composite* source (DataCross/Zip/Union) is NOT
     * honored — PHPUnit ignores it, so its method's parameters must still be dropped.
     */
    private function hasHonoredDataSource(ClassMethod $method): bool
    {
        return $this->hasAttribute($method, [
            'PHPUnit\\Framework\\Attributes\\DataProvider',
            'PHPUnit\\Framework\\Attributes\\DataProviderExternal',
            'PHPUnit\\Framework\\Attributes\\TestWith',
            'PHPUnit\\Framework\\Attributes\\TestWithJson',
            'Testo\\Data\\DataProvider',
            'Testo\\Data\\DataSet',
        ]);
    }

    private function isAlreadySkipped(ClassMethod $method): bool
    {
        $first = ($method->stmts ?? [])[0] ?? null;
        if (!$first instanceof Expression) {
            return false;
        }

        # `$this->markTestSkipped()` on a test method, `self::markTestSkipped()` on a (possibly static) hook.
        $call = $first->expr;

        return ($call instanceof MethodCall || $call instanceof StaticCall)
            && $this->isName($call->name, 'markTestSkipped');
    }

    /**
     * @param list<string> $names
     */
    private function hasAttribute(ClassMethod $method, array $names): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if ($this->isAnyName($attr->name, $names)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<string> $names
     */
    private function isAnyName(Name $name, array $names): bool
    {
        $resolved = $this->getName($name);

        return $resolved !== null && \in_array($resolved, $names, true);
    }
}
