<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\TestoPolish;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitor;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Rector\StaticTypeMapper\ValueObject\Type\FullyQualifiedObjectType;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use Testo\Bridge\Rector\Testing\TestRectorFixtures;

/**
 * Imports the fully qualified names the conversion sets write: Testo's (`\Testo\...`, including the
 * Double and Mockery bridges), Mockery's (`\Mockery`, `\Mockery\...`) and Double's
 * (`\JMac\Testing\...`). Every other name stays as written, whatever Rector's own import settings.
 *
 * A name is shortened to an existing import or alias of the same class, or gets a new `use`. It
 * stays fully qualified when its short name is taken: by an import of another class, a class-like
 * declared in the file, a class of the same namespace, or another class the file already refers
 * to by that short name. Docblocks keep their names as written.
 *
 * An import from `PHPUnit\` or Mockery is removed once nothing in the file refers to it: no class
 * reference of any kind (attribute, `new`, `::class`, static call, type, `instanceof`, `catch`) and
 * no mention in a docblock.
 *
 * The file is indexed when its root node is visited, before the other rules of the run change it;
 * a name another rule writes later is still seen, since names are visited after their parents.
 * A file with more than one namespace is left alone.
 */
#[TestRectorFixtures('ImportTestoNamesRector')]
final class ImportTestoNamesRector extends AbstractRector
{
    /** Lowercased namespace prefixes and classes whose names get imported. */
    private const IMPORTED = ['testo\\', 'mockery\\', 'jmac\\testing\\', 'mockery'];

    /** Lowercased namespace prefixes and classes whose unused imports get removed. */
    private const DROPPED_WHEN_UNUSED = ['phpunit\\', 'mockery\\', 'mockery'];

    private const SKIP = 'testo_import_skip';

    private ?FileNode $fileNode = null;
    private ?string $namespace = null;

    /** @var array<lowercase-string, array{string, string}> Alias => [alias as written, imported class]. */
    private array $aliases = [];

    /** @var array<lowercase-string, true> Short names a new import must not take. */
    private array $taken = [];

    /** @var array<lowercase-string, list<lowercase-string>> Short name => classes the file writes it for. */
    private array $shortRefs = [];

    /** @var array<int, true> Object ids of the unused import items to remove. */
    private array $unused = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Import the fully qualified Testo, Mockery and Double names and drop unused PHPUnit imports',
            [
                new CodeSample(
                    <<<'PHP'
                        namespace App\Tests;

                        use PHPUnit\Framework\Attributes\Test;

                        final class OrderTest
                        {
                            #[\Testo\Test]
                            public function createsOrder(): void
                            {
                                \Testo\Assert::same(1, 1);
                            }
                        }
                        PHP,
                    <<<'PHP'
                        namespace App\Tests;

                        use Testo\Test;
                        use Testo\Assert;

                        final class OrderTest
                        {
                            #[Test]
                            public function createsOrder(): void
                            {
                                Assert::same(1, 1);
                            }
                        }
                        PHP,
                ),
            ],
        );
    }

    #[\Override]
    public function getNodeTypes(): array
    {
        return [FileNode::class, Use_::class, FuncCall::class, ConstFetch::class, FullyQualified::class];
    }

    /**
     * @param FileNode|Use_|FuncCall|ConstFetch|FullyQualified $node
     */
    #[\Override]
    public function refactor(Node $node): Node|int|null
    {
        return match (true) {
            $node instanceof FileNode => $this->index($node),
            $node instanceof Use_ => $this->dropUnused($node),
            $node instanceof FullyQualified => $this->import($node),
            default => $this->skipName($node),
        };
    }

    private function index(FileNode $file): null
    {
        $this->fileNode = null;
        $this->namespace = null;
        $this->aliases = $this->taken = $this->shortRefs = $this->unused = [];

        $namespaces = \array_values(\array_filter($file->stmts, static fn(Stmt $stmt): bool => $stmt instanceof Namespace_));
        if (\count($namespaces) > 1) {
            return null;
        }

        $scope = $namespaces[0] ?? $file;
        $this->fileNode = $file;
        $this->namespace = $scope instanceof Namespace_ ? (string) $scope->name?->toString() : '';

        $body = \array_values(\array_filter(
            $scope->stmts,
            static fn(Stmt $stmt): bool => !$stmt instanceof Use_ && !$stmt instanceof GroupUse && !$stmt instanceof Namespace_,
        ));
        $finder = new NodeFinder();

        $firstSegments = [];
        foreach ($finder->findInstanceOf($body, Name::class) as $name) {
            $written = $this->writtenShort($name);
            if ($written === null || $written->isSpecialClassName()) {
                continue;
            }

            $first = \strtolower($written->getFirst());
            $firstSegments[$first] = true;
            $written->isUnqualified() and $this->shortRefs[$first][] = \strtolower($this->resolved($name));
        }

        $docs = '';
        foreach ($finder->find($body, static fn(Node $node): bool => $node->getComments() !== []) as $node) {
            foreach ($node->getComments() as $comment) {
                $comment instanceof Doc and $docs .= $comment->getText() . "\n";
            }
        }

        foreach ($finder->findInstanceOf($body, ClassLike::class) as $classLike) {
            $classLike->name === null or $this->taken[$classLike->name->toLowerString()] = true;
        }

        foreach ($scope->stmts as $stmt) {
            if ($stmt instanceof GroupUse && \in_array($stmt->type, [Use_::TYPE_NORMAL, Use_::TYPE_UNKNOWN], true)) {
                foreach ($stmt->uses as $use) {
                    $use->type === Use_::TYPE_FUNCTION || $use->type === Use_::TYPE_CONSTANT
                        or $this->aliases[$use->getAlias()->toLowerString()] = [
                            $use->getAlias()->toString(),
                            $stmt->prefix->toString() . '\\' . $use->name->toString(),
                        ];
                }
                continue;
            }

            if (!$stmt instanceof Use_ || $stmt->type !== Use_::TYPE_NORMAL) {
                continue;
            }

            foreach ($stmt->uses as $use) {
                $alias = $use->getAlias()->toLowerString();
                $class = $use->name->toString();
                if ($this->matches($class, self::DROPPED_WHEN_UNUSED)
                    && !isset($firstSegments[$alias])
                    && \preg_match('/(?<![\\\\\w$])' . \preg_quote($alias, '/') . '(?!\w)/i', $docs) !== 1
                ) {
                    $this->unused[\spl_object_id($use)] = true;
                    continue;
                }

                $this->aliases[$alias] = [$use->getAlias()->toString(), $class];
            }
        }

        return null;
    }

    private function dropUnused(Use_ $use): Use_|int|null
    {
        if ($this->unused === []) {
            return null;
        }

        $kept = \array_values(\array_filter($use->uses, fn(Node $item): bool => !isset($this->unused[\spl_object_id($item)])));
        if (\count($kept) === \count($use->uses)) {
            return null;
        }

        if ($kept === []) {
            return NodeVisitor::REMOVE_NODE;
        }

        $use->uses = $kept;

        return $use;
    }

    /**
     * Function and constant names look like class names but must never be imported as one.
     */
    private function skipName(FuncCall|ConstFetch $node): null
    {
        $node->name instanceof Name and $node->name->setAttribute(self::SKIP, true);

        return null;
    }

    private function import(FullyQualified $name): ?Name
    {
        if ($this->fileNode === null
            || $name->getAttribute(self::SKIP) === true
            || $this->writtenShort($name) !== null
            || !$this->matches($name->toString(), self::IMPORTED)
        ) {
            return null;
        }

        $class = $name->toString();
        foreach ($this->aliases as [$alias, $imported]) {
            if (\strcasecmp($imported, $class) === 0) {
                return $this->shortName($alias, $class);
            }
        }

        $short = $name->getLast();
        $key = \strtolower($short);
        $sameNamespace = \ltrim($this->namespace . '\\' . $short, '\\');

        if (\strcasecmp($sameNamespace, $class) === 0) {
            return $this->namespace === '' ? null : $this->shortName($short, $class);
        }

        if ($this->namespace === '' && $name->isUnqualified()
            || isset($this->aliases[$key])
            || isset($this->taken[$key])
            || \array_diff($this->shortRefs[$key] ?? [], [\strtolower($class)]) !== []
            || $this->reflectionProvider->hasClass($sameNamespace)
        ) {
            return null;
        }

        $this->aliases[$key] = [$short, $class];
        $this->fileNode->getPendingImports()->addUseImport(new FullyQualifiedObjectType($class));

        return $this->shortName($short, $class);
    }

    /**
     * The name as the source wrote it, when not fully qualified. Rector resolves a written short name
     * to a fully qualified node and keeps the written one aside; a node a rule created has neither.
     */
    private function writtenShort(Name $name): ?Name
    {
        if (!$name instanceof FullyQualified) {
            return $name;
        }

        $original = $name->getAttribute(AttributeKey::ORIGINAL_NAME);

        return $original instanceof Name && !$original instanceof FullyQualified ? $original : null;
    }

    private function resolved(Name $name): string
    {
        if ($name instanceof FullyQualified) {
            return $name->toString();
        }

        $namespaced = $name->getAttribute(AttributeKey::NAMESPACED_NAME);

        return \is_string($namespaced) ? $namespaced : $name->toString();
    }

    /**
     * @param list<lowercase-string> $prefixes Namespace prefixes ending in `\`, or exact class names.
     */
    private function matches(string $class, array $prefixes): bool
    {
        $class = \strtolower(\ltrim($class, '\\'));
        foreach ($prefixes as $prefix) {
            if ($class === $prefix || \str_ends_with($prefix, '\\') && \str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function shortName(string $alias, string $class): Name
    {
        $name = new Name($alias);
        $name->setAttribute(AttributeKey::NAMESPACED_NAME, $class);

        return $name;
    }
}
