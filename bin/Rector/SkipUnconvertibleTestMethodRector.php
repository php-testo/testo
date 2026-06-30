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
use PhpParser\NodeFinder;
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
 *   - its class lives in a runtime/layout-bound namespace ({@see self::RUNTIME_NAMESPACES}) — the
 *     Tokenizer self-tests assert on names the `Tests\PhpUnit` relocation rewrites, the Facade
 *     tests need an active container;
 *   - its class drives the Testo engine via `Testo\Testing\` anywhere (e.g. `TestRunner` used
 *     directly or through a private helper) — then EVERY test method of the class is skipped;
 *   - it carries a composite data source ({@see self::COMPOSITE_DATA}) with no PHPUnit equivalent.
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
        'Tests\\PhpUnit\\Tokenizer' => 'inspects tokenized source and asserts on names the Tests\\PhpUnit relocation rewrites',
        'Tests\\PhpUnit\\Facade' => 'depends on an active Testo\\Facade container, which only the FacadePlugin runtime provides',
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
        // Class-wide reasons skip every test method (the whole case is runtime/layout-bound);
        // otherwise each method is judged on its own (composite data source).
        $classReason = $this->namespaceReason($this->getName($node)) ?? $this->runtimeReason($node);

        $changed = false;
        foreach ($node->getMethods() as $method) {
            if (!$this->isRunnableTestMethod($method) || $this->isAlreadySkipped($method)) {
                continue;
            }

            $reason = $classReason ?? $this->methodReason($method);
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

    /**
     * Class-wide reason: the class touches `Testo\Testing\` anywhere (a test body OR a private
     * helper it calls), so the whole case drives the engine and every test method must be skipped.
     */
    private function runtimeReason(Class_ $class): ?string
    {
        $usesRuntime = (new NodeFinder())->findFirst(
            [$class],
            fn(Node $n): bool => $n instanceof Name
                && ($name = $this->getName($n)) !== null
                && \str_starts_with($name, 'Testo\\Testing\\'),
        );

        return $usesRuntime !== null
            ? 'exercises the Testo runtime (Testo\\Testing\\) and has no PHPUnit equivalent'
            : null;
    }

    private function methodReason(ClassMethod $method): ?string
    {
        return $this->hasAttribute($method, self::COMPOSITE_DATA)
            ? 'uses a composite data source (Testo\\Data\\DataCross/DataZip/DataUnion) with no PHPUnit equivalent'
            : null;
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
