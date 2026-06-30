<?php

declare(strict_types=1);

namespace Testo\PhpUnitBuild\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Build-only local rule: rename a test method whose name collides with a `final` method PHPUnit's
 * TestCase inherits, so the class can extend TestCase without a "Cannot override final method" fatal
 * at load time.
 *
 * A Testo test needs no base class, so a helper may be named `run()`, `isIterable()`, etc. — all of
 * which TestCase/Assert declare `final`. The method (and every `$this->`/`self::`/`static::` call to
 * it within the same class) is renamed by appending `_` until the name no longer collides and is
 * free in the class. The final method names are injected via configuration (the rector config reads
 * them by reflection from the isolated tools/phpunit install at boot — native reflection inside a
 * running rule is unreliable, returning an empty set).
 *
 * Only classes that already extend `\PHPUnit\Framework\TestCase` are touched (run after
 * TestClassToTestCaseRector); calls through other objects or callable arrays are not rewritten.
 */
final class RenameFinalMethodCollisionRector extends AbstractRector implements ConfigurableRectorInterface
{
    /** @var array<string, true> lowercased name => true for every final method TestCase inherits */
    private array $finalMethods = [];

    /**
     * @param list<string> $configuration final method names
     */
    public function configure(array $configuration): void
    {
        foreach ($configuration as $name) {
            $this->finalMethods[\strtolower((string) $name)] = true;
        }
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Rename a test method colliding with a final TestCase method (and its in-class calls)',
            [
                new CodeSample(
                    <<<'PHP'
                        public function run(): void { $this->run(); }
                        PHP,
                    <<<'PHP'
                        public function run_(): void { $this->run_(); }
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

        $finals = $this->finalMethods;

        /** @var array<string, string> $renames lowercased old name => new name */
        $renames = [];
        foreach ($node->getMethods() as $method) {
            $name = $this->getName($method);
            if ($name === null || !isset($finals[\strtolower($name)])) {
                continue;
            }

            $new = $name;
            do {
                $new .= '_';
            } while (isset($finals[\strtolower($new)]) || $node->getMethod($new) !== null);

            $method->name = new Identifier($new);
            $renames[\strtolower($name)] = $new;
        }

        if ($renames === []) {
            return null;
        }

        $this->renameInternalCalls($node, $renames);

        return $node;
    }

    /**
     * @param array<string, string> $renames
     */
    private function renameInternalCalls(Class_ $node, array $renames): void
    {
        $finder = new NodeFinder();
        $self = [$this->getName($node), 'self', 'static'];

        /** @var MethodCall $call */
        foreach ($finder->findInstanceOf([$node], MethodCall::class) as $call) {
            if ($call->name instanceof Identifier
                && isset($renames[\strtolower($call->name->name)])
                && $call->var instanceof Variable
                && $this->isName($call->var, 'this')
            ) {
                $call->name = new Identifier($renames[\strtolower($call->name->name)]);
            }
        }

        /** @var StaticCall $call */
        foreach ($finder->findInstanceOf([$node], StaticCall::class) as $call) {
            if ($call->name instanceof Identifier
                && isset($renames[\strtolower($call->name->name)])
                && $call->class instanceof Name
                && \in_array($this->getName($call->class), $self, true)
            ) {
                $call->name = new Identifier($renames[\strtolower($call->name->name)]);
            }
        }
    }
}
