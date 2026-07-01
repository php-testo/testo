<?php

declare(strict_types=1);

namespace Testo\PhpUnitBuild\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
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
 *     tests need an active container;
 *   - it carries a composite data source ({@see self::COMPOSITE_DATA}) with no PHPUnit equivalent;
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
 * Skipping rewrites the body to a single `$this->markTestSkipped(...)`, drops the parameters and
 * removes any data-provider attributes — otherwise PHPUnit would invoke the method with the wrong
 * argument count before the skip runs. Whole-file fatals (a custom constructor, a name colliding
 * with a `final` TestCase method) are handled earlier by bin/build-phpunit.php, which cannot be
 * fixed per method.
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

    /** Argument-feeding attributes to strip from a skipped method (else PHPUnit miscounts args). */
    private const DATA_ATTRIBUTES = [
        'PHPUnit\\Framework\\Attributes\\DataProvider',
        'PHPUnit\\Framework\\Attributes\\TestWith',
        'PHPUnit\\Framework\\Attributes\\TestWithJson',
        'Testo\\Data\\DataProvider',
        'Testo\\Data\\DataSet',
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
                        public function risky(): void
                        {
                            $result = \Testo\Testing\Helper\TestRunner::runTest([Common::class, 'risky']);
                            $this->assertSame($result->status, Status::Risky);
                        }
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Test]
                        public function risky(): void
                        {
                            $this->markTestSkipped('risky() exercises the Testo runtime (Testo\Testing\) and has no PHPUnit equivalent');
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

        // When the whole case is skipped, its lifecycle hooks still run before/after each (skipped)
        // test. Empty them: their setup is meaningless once every test is skipped, and it may
        // reference Testo runtime helpers or stub files absent from the mirror (e.g. a former
        // constructor turned into a #[Before] hook that `require`s a stub).
        if ($classReason !== null) {
            foreach ($node->getMethods() as $method) {
                // Leave a hook already turned into a skipped test (it carried #[Test]) — emptying it
                // would drop the markTestSkipped and PHPUnit would report it risky, not skipped.
                if ($this->isLifecycleHook($method) && !$this->isAlreadySkipped($method) && ($method->stmts ?? []) !== []) {
                    $method->stmts = [];
                    $changed = true;
                }
            }
        }

        return $changed ? $node : null;
    }

    /** A lifecycle hook by reserved name or by PHPUnit lifecycle attribute. */
    private function isLifecycleHook(ClassMethod $method): bool
    {
        if (\in_array(\strtolower((string) $this->getName($method)), ['setup', 'teardown', 'setupbeforeclass', 'teardownafterclass'], true)) {
            return true;
        }

        return $this->hasAttribute($method, [
            'PHPUnit\\Framework\\Attributes\\Before',
            'PHPUnit\\Framework\\Attributes\\After',
            'PHPUnit\\Framework\\Attributes\\BeforeClass',
            'PHPUnit\\Framework\\Attributes\\AfterClass',
        ]);
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

        $shortClass = $fqcn === null ? '' : \substr($fqcn, (int) \strrpos($fqcn, '\\') + 1);

        return self::SKIP_METHODS[$shortClass . '::' . $this->getName($method)] ?? null;
    }

    private function skip(ClassMethod $method, string $reason): void
    {
        $name = $this->getName($method) ?? 'test';

        $method->params = [];
        $method->stmts = [
            new Expression(new MethodCall(
                new Variable('this'),
                new Identifier('markTestSkipped'),
                [new Arg(new String_("{$name}() {$reason}"))],
            )),
        ];

        // Drop data-feeding attributes (and any group left empty), keep the rest (#[Test], #[Group]).
        $groups = [];
        foreach ($method->attrGroups as $group) {
            $group->attrs = \array_filter(
                $group->attrs,
                fn($attr): bool => !$this->isAnyName($attr->name, self::DATA_ATTRIBUTES),
            );

            if ($group->attrs !== []) {
                $group->attrs = \array_values($group->attrs);
                $groups[] = $group;
            }
        }

        $method->attrGroups = $groups;
    }

    private function isAlreadySkipped(ClassMethod $method): bool
    {
        if (\count($method->stmts ?? []) !== 1) {
            return false;
        }

        $stmt = $method->stmts[0];

        return $stmt instanceof Expression
            && $stmt->expr instanceof MethodCall
            && $this->isName($stmt->expr->name, 'markTestSkipped');
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
