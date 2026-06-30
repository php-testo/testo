<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\PhpunitToTesto;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Detaches a PHPUnit test class from its base class and makes it discoverable by Testo.
 *
 * For a class that **directly** `extends \PHPUnit\Framework\TestCase` (whether written
 * fully-qualified, as a bare imported `TestCase`, or aliased), this rule:
 *   - removes the `extends` clause, and
 *   - marks discovery as attribute-based by adding `#[\Testo\Test]` to every test method.
 *
 * "Test method" mirrors PHPUnit's own discovery: a method carrying the PHPUnit
 * `#[\PHPUnit\Framework\Attributes\Test]` attribute, a method with a `@test` docblock
 * annotation, or a method whose name starts with `test`. The PHPUnit `#[Test]` attribute
 * is rewritten to `#[\Testo\Test]`; a `@test` annotation is removed and replaced by the
 * attribute; a bare `test`-prefixed method simply gains the attribute. The rule is
 * idempotent — a method that already carries `#[\Testo\Test]` is left as-is.
 *
 * Scope: only a class that extends a PHPUnit `TestCase` *directly* is converted. A class
 * extending an intermediate/custom base (even one that itself extends `TestCase`) is left
 * untouched — its base class is the right place to convert, and chasing the hierarchy here
 * would be unsafe.
 *
 * Residual: methods are NOT renamed. Keeping `testFoo()` is harmless under Testo (discovery
 * is by attribute, not name), but call-site rewriting / cleanup of the `test` prefix is out
 * of scope and left for manual follow-up.
 */
#[TestRectorFixtures('ExtendsTestCaseToTestoRector')]
final class ExtendsTestCaseToTestoRector extends AbstractRector
{
    public function __construct(
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly PhpDocTagRemover $phpDocTagRemover,
        private readonly DocBlockUpdater $docBlockUpdater,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove "extends PHPUnit\\Framework\\TestCase" and make the class attribute-discoverable by adding #[\\Testo\\Test] to its test methods',
            [
                new CodeSample(
                    <<<'PHP'
                        final class MyTest extends \PHPUnit\Framework\TestCase
                        {
                            public function testFoo(): void {}
                        }
                        PHP,
                    <<<'PHP'
                        final class MyTest
                        {
                            #[\Testo\Test]
                            public function testFoo(): void {}
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

        $node->extends = null;

        foreach ($node->getMethods() as $method) {
            $method->isPublic() and $this->markTestMethod($method);
        }

        return $node;
    }

    private function markTestMethod(ClassMethod $method): void
    {
        # Idempotent: a method already carrying #[\Testo\Test] needs nothing.
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'Testo\\Test')) {
                    return;
                }
            }
        }

        # PHPUnit #[Test] attribute -> rewrite in place to #[\Testo\Test].
        foreach ($method->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                if ($this->isName($attr->name, 'PHPUnit\\Framework\\Attributes\\Test')) {
                    $attr->name = new FullyQualified('Testo\\Test');

                    return;
                }
            }
        }

        # @test annotation -> drop the tag and add the attribute.
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($method);
        if ($phpDocInfo instanceof PhpDocInfo) {
            $testTags = $phpDocInfo->getTagsByName('test');
            if ($testTags !== []) {
                foreach ($testTags as $tag) {
                    $tag->value instanceof GenericTagValueNode and $this->phpDocTagRemover->removeTagValueFromNode($phpDocInfo, $tag);
                }
                $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($method);
                $this->addTestoAttribute($method);

                return;
            }
        }

        # `test`-prefixed name with no explicit marker -> add the attribute.
        $name = $this->getName($method->name);
        if ($name !== null && \str_starts_with($name, 'test')) {
            $this->addTestoAttribute($method);
        }
    }

    private function addTestoAttribute(ClassMethod $method): void
    {
        $method->attrGroups[] = new AttributeGroup([
            new Attribute(new FullyQualified('Testo\\Test')),
        ]);
    }
}
