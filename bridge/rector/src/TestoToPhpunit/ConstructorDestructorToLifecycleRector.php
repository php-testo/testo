<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Turns a Testo test class's `__construct()`/`__destruct()` into PHPUnit lifecycle hooks:
 * `#[Before]`/`#[After]` methods.
 *
 * A Testo test needs no base class, so per-test setup/teardown is often done in the constructor /
 * destructor. PHPUnit owns `TestCase::__construct(string $name)`, so a custom constructor leaves the
 * test unrunnable. Rather than fold into `setUp()` (which collides with an existing `setUp`, forces
 * a `parent::setUp()` call, and reuses a reserved name), the method is RENAMED to a fresh name and
 * tagged with the lifecycle attribute — PHPUnit runs every `#[Before]`/`#[After]` method, so it
 * coexists with any existing `setUp()`/hook:
 *
 *     public function __construct() { $this->svc = new Service(); }
 *     // becomes
 *     #[\PHPUnit\Framework\Attributes\Before]
 *     protected function setUpFromConstructor(): void { $this->svc = new Service(); }
 *
 * `#[Before]`/`#[After]` run before/after EACH test (like setUp/tearDown). Testo's constructor runs
 * once per case, but PHPUnit instantiates per test, so there is no per-case instance hook anyway
 * (`setUpBeforeClass` is static); `#[Before]` is the faithful instance equivalent.
 *
 * Guards: only classes extending `\PHPUnit\Framework\TestCase` (pair after TestClassToTestCaseRector);
 * only a PARAMETERLESS constructor (parameters — promoted properties / DI — have no hook equivalent);
 * a constructor on a nested/anonymous class is left alone.
 */
#[TestRectorFixtures('ConstructorDestructorToLifecycleRector')]
final class ConstructorDestructorToLifecycleRector extends AbstractRector
{
    /**
     * Magic method => [PHPUnit lifecycle attribute, generated method base name].
     *
     * @var array<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    private const MAP = [
        '__construct' => ['PHPUnit\\Framework\\Attributes\\Before', 'setUpFromConstructor'],
        '__destruct' => ['PHPUnit\\Framework\\Attributes\\After', 'tearDownFromDestructor'],
    ];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            "Convert a test class's parameterless __construct()/__destruct() into #[Before]/#[After] lifecycle methods",
            [
                new CodeSample(
                    <<<'PHP'
                        public function __construct()
                        {
                            $this->svc = new Service();
                        }
                        PHP,
                    <<<'PHP'
                        #[\PHPUnit\Framework\Attributes\Before]
                        protected function setUpFromConstructor(): void
                        {
                            $this->svc = new Service();
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
        if ($node->extends === null || !$this->isName($node->extends, 'PHPUnit\\Framework\\TestCase')) {
            return null;
        }

        $changed = false;
        foreach (self::MAP as $magic => [$attribute, $baseName]) {
            $method = $node->getMethod($magic);
            if ($method === null || $method->params !== []) {
                continue;
            }

            $method->name = new Identifier($this->uniqueMethodName($node, $baseName));
            $method->flags = Modifiers::PROTECTED;
            $method->returnType = new Identifier('void');
            $method->attrGroups[] = new AttributeGroup([new Attribute(new FullyQualified($attribute))]);

            $changed = true;
        }

        return $changed ? $node : null;
    }

    private function uniqueMethodName(Class_ $node, string $base): string
    {
        $name = $base;
        while ($node->getMethod($name) !== null) {
            $name .= '_';
        }

        return $name;
    }
}
