<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoToPhpunit;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Internal\DroppedAttributes;
use Testo\Bridge\Rector\Internal\OriginalTokens;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Drops Testo's `#[\Testo\Common\Attribute\AssertMethod]` from custom assertion helpers, together with
 * its import once nothing else in the file refers to it.
 *
 * PHPUnit filters its own frames from a failure's trace without a marker, so the helper reports
 * from its own frame with no attribute at all; keeping it would only tie the code to Testo.
 *
 * @api
 */
#[TestRectorFixtures('AssertMethodToPhpUnitRector')]
final class AssertMethodToPhpUnitRector extends AbstractRector
{
    use OriginalTokens;

    private const ATTRIBUTE = 'Testo\\Common\\Attribute\\AssertMethod';

    /** @var array<int, true> Object ids of the use items to drop in the current file. */
    private array $unusedImports = [];

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Remove Testo #[\Testo\Common\Attribute\AssertMethod] from custom assertion helpers',
            [
                new CodeSample(
                    <<<'PHP'
                        #[\Testo\Common\Attribute\AssertMethod]
                        protected function assertValidUser(User $user): void
                        {
                            \Testo\Assert::true($user->isValid());
                        }
                        PHP,
                    <<<'PHP'
                        protected function assertValidUser(User $user): void
                        {
                            \Testo\Assert::true($user->isValid());
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [FileNode::class, Use_::class, GroupUse::class, ClassMethod::class, Function_::class];
    }

    /**
     * @param FileNode|Use_|GroupUse|ClassMethod|Function_ $node
     */
    #[\Override]
    public function refactor(Node $node): Node|int|null
    {
        return match (true) {
            $node instanceof FileNode => $this->collectUnusedImports($node),
            $node instanceof Use_, $node instanceof GroupUse => $this->dropUnusedImports($node),
            default => $this->dropAttribute($node),
        };
    }

    /**
     * The file node is visited before its imports, so the decision is taken here: an import goes when
     * every other reference to the attribute is one of the attributes this rule drops.
     */
    private function collectUnusedImports(FileNode $file): null
    {
        $this->unusedImports = [];
        $finder = new NodeFinder();

        $skipped = [];
        foreach ($finder->find($file->stmts, static fn(Node $node): bool => $node instanceof ClassMethod || $node instanceof Function_) as $function) {
            \assert($function instanceof ClassMethod || $function instanceof Function_);
            foreach ($function->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    $this->isName($attr->name, self::ATTRIBUTE) and $skipped[\spl_object_id($attr->name)] = true;
                }
            }
        }

        $imports = [];
        foreach ($finder->find($file->stmts, static fn(Node $node): bool => $node instanceof Use_ || $node instanceof GroupUse) as $use) {
            \assert($use instanceof Use_ || $use instanceof GroupUse);
            $prefix = $use instanceof GroupUse ? $use->prefix->toString() . '\\' : '';
            $use instanceof GroupUse and $skipped[\spl_object_id($use->prefix)] = true;
            foreach ($use->uses as $item) {
                $skipped[\spl_object_id($item->name)] = true;
                $type = $use->type === Use_::TYPE_UNKNOWN ? $item->type : $use->type;
                $type === Use_::TYPE_NORMAL && \strcasecmp($prefix . $item->name->toString(), self::ATTRIBUTE) === 0
                    and $imports[] = $item;
            }
        }

        if ($imports === []) {
            return null;
        }

        foreach ($finder->findInstanceOf($file->stmts, Name::class) as $name) {
            if (!isset($skipped[\spl_object_id($name)]) && $this->isName($name, self::ATTRIBUTE)) {
                return null;
            }
        }

        foreach ($imports as $item) {
            $alias = \preg_quote($item->getAlias()->toString(), '/');
            foreach ($finder->find($file->stmts, static fn(Node $node): bool => $node->getComments() !== []) as $commented) {
                foreach ($commented->getComments() as $comment) {
                    if ($comment instanceof Doc && \preg_match('/(?<![\\\\\w$])' . $alias . '(?!\w)/i', $comment->getText()) === 1) {
                        continue 3;
                    }
                }
            }

            $this->unusedImports[\spl_object_id($item)] = true;
        }

        return null;
    }

    private function dropUnusedImports(Use_|GroupUse $use): Use_|GroupUse|int|null
    {
        $kept = \array_values(\array_filter($use->uses, fn(Node $item): bool => !isset($this->unusedImports[\spl_object_id($item)])));
        if (\count($kept) === \count($use->uses)) {
            return null;
        }

        if ($kept === []) {
            return NodeVisitor::REMOVE_NODE;
        }

        $use->uses = $kept;

        return $use;
    }

    private function dropAttribute(ClassMethod|Function_ $node): ?Node
    {
        $dropped = false;
        $kept = [];
        foreach ($node->attrGroups as $attrGroup) {
            $attrs = \array_values(\array_filter(
                $attrGroup->attrs,
                fn(Attribute $attr): bool => !$this->isName($attr->name, self::ATTRIBUTE),
            ));
            \count($attrs) === \count($attrGroup->attrs) or $dropped = true;
            $attrGroup->attrs = $attrs;
            $attrs === [] or $kept[] = $attrGroup;
        }

        if (!$dropped) {
            return null;
        }

        $node->attrGroups = $kept;
        DroppedAttributes::keepFormat($node, $this->originalTokens());

        return $node;
    }
}
