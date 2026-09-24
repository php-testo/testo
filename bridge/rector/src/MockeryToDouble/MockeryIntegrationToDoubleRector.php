<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\MockeryToDouble;

use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\TraitUse;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Moves a PHPUnit test class off Mockery's verification hooks onto Double's:
 *
 * - `use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;` → `use \JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;`
 * - `extends \Mockery\Adapter\Phpunit\MockeryTestCase` → `extends \PHPUnit\Framework\TestCase` plus the
 *   `VerifiesDoubles` trait.
 *
 * Both close Mockery's container after each test; `VerifiesDoubles` verifies the test's doubles the same
 * way. A class overriding `MockeryTestCase`'s own `mockeryTestSetUp()`/`mockeryTestTearDown()` hooks is
 * left alone — those hooks would stop being called. On Testo, the `testo/bridge-double` plugin does this
 * job instead and the class needs neither.
 */
#[TestRectorFixtures('MockeryIntegrationToDoubleRector')]
final class MockeryIntegrationToDoubleRector extends AbstractRector
{
    private const INTEGRATION_TRAIT = 'Mockery\\Adapter\\Phpunit\\MockeryPHPUnitIntegration';
    private const TEST_CASE = 'Mockery\\Adapter\\Phpunit\\MockeryTestCase';
    private const VERIFIES_DOUBLES = 'JMac\\Testing\\Integrations\\PHPUnit\\VerifiesDoubles';

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Replace Mockery\'s PHPUnit integration (`MockeryPHPUnitIntegration`, `MockeryTestCase`) with Double\'s `VerifiesDoubles`',
            [
                new CodeSample(
                    <<<'PHP'
                        final class SomeTest extends \PHPUnit\Framework\TestCase
                        {
                            use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
                        }
                        PHP,
                    <<<'PHP'
                        final class SomeTest extends \PHPUnit\Framework\TestCase
                        {
                            use \JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
                        }
                        PHP,
                ),
            ],
        );
    }

    /**
     * @return array<class-string<Node>>
     */
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
        $changed = false;
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof TraitUse || $stmt->adaptations !== []) {
                continue;
            }

            foreach ($stmt->traits as $index => $trait) {
                if ($this->isName($trait, self::INTEGRATION_TRAIT)) {
                    $stmt->traits[$index] = new FullyQualified(self::VERIFIES_DOUBLES);
                    $changed = true;
                }
            }
        }

        if ($node->extends !== null && $this->isName($node->extends, self::TEST_CASE) && !$this->overridesMockeryHooks($node)) {
            $node->extends = new FullyQualified('PHPUnit\\Framework\\TestCase');
            if (!$this->usesVerifiesDoubles($node)) {
                \array_unshift($node->stmts, new TraitUse([new FullyQualified(self::VERIFIES_DOUBLES)]));
            }
            $changed = true;
        }

        return $changed ? $node : null;
    }

    private function overridesMockeryHooks(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && ($this->isName($stmt, 'mockeryTestSetUp') || $this->isName($stmt, 'mockeryTestTearDown'))) {
                return true;
            }
        }

        return false;
    }

    private function usesVerifiesDoubles(Class_ $class): bool
    {
        foreach ($class->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    if ($this->isName($trait, self::VERIFIES_DOUBLES)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
