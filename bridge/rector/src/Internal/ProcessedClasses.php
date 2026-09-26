<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;

/**
 * An index of the classes, traits and enums declared within the paths Rector processes: which class
 * extends which, which traits each of them uses, and which classes carry `#[\Testo\Test]`.
 *
 * Reflection cannot list the subclasses of a class or the users of a trait, so the processed files
 * are parsed on the first question and indexed as they are on disk at that moment. A declaration
 * outside the processed paths stays unknown, so every answer errs on the side of "no".
 *
 * @internal
 */
final class ProcessedClasses
{
    private const TEST = 'testo\\test';

    /** @var list<string>|null The processed paths the index was built from. */
    private ?array $indexedPaths = null;

    /**
     * Keyed by the lowercased name; an anonymous class gets a synthetic key.
     *
     * @var array<string, array{
     *     parent: lowercase-string|null,
     *     classLevelTest: bool,
     *     isClass: bool,
     *     traits: list<string>,
     *     adaptsTraits: bool,
     * }>
     */
    private array $types = [];

    /** @var array<lowercase-string, true> Lowercased names of the extended classes. */
    private array $extended = [];

    public function hasSubclass(string $class): bool
    {
        $this->ensureIndexed();

        return isset($this->extended[self::key($class)]);
    }

    /**
     * Whether the trait has users and every class that takes it in, directly or through another trait,
     * selects tests by a class-level `#[\Testo\Test]`, its own or inherited. A user that renames or
     * hides the trait's methods (`insteadof`, `as`), an enum, and a trait nobody uses answer "no".
     */
    public function isUsedOnlyByClassLevelTests(string $trait): bool
    {
        $this->ensureIndexed();

        return $this->traitUsersAreClassLevel('trait:' . self::key($trait), []) === true;
    }

    /**
     * @return lowercase-string
     */
    private static function key(string $name): string
    {
        return \strtolower(\ltrim($name, '\\'));
    }

    /**
     * @param array<string, true> $visiting Traits on the current path, to stop at a cycle.
     * @return bool|null Null when the trait has no users.
     */
    private function traitUsersAreClassLevel(string $trait, array $visiting): ?bool
    {
        if (isset($visiting[$trait])) {
            return false;
        }
        $visiting[$trait] = true;

        $hasUsers = false;
        foreach ($this->types as $name => $type) {
            if (!\in_array($trait, $type['traits'], true)) {
                continue;
            }

            $hasUsers = true;
            if ($type['adaptsTraits']) {
                return false;
            }

            $classLevel = match (true) {
                $type['isClass'] => $this->isClassLevel($name, []),
                $this->isTrait($name) => $this->traitUsersAreClassLevel($name, $visiting) === true,
                default => false,
            };

            if (!$classLevel) {
                return false;
            }
        }

        return $hasUsers ? true : null;
    }

    /**
     * @param array<string, true> $visiting Classes on the current path, to stop at a cycle.
     */
    private function isClassLevel(string $class, array $visiting): bool
    {
        $type = $this->types[$class] ?? null;
        if ($type === null || isset($visiting[$class])) {
            return false;
        }

        if ($type['classLevelTest']) {
            return true;
        }

        $visiting[$class] = true;

        return $type['parent'] !== null && $this->isClassLevel($type['parent'], $visiting);
    }

    private function isTrait(string $name): bool
    {
        return \str_starts_with($name, 'trait:');
    }

    private function ensureIndexed(): void
    {
        $paths = SimpleParameterProvider::provideArrayParameter(Option::SOURCE);
        if ($paths === $this->indexedPaths) {
            return;
        }

        $this->indexedPaths = $paths;
        $this->types = [];
        $this->extended = [];

        $parser = (new ParserFactory())->createForHostVersion();
        foreach ($paths as $path) {
            foreach ($this->phpFiles($path) as $file) {
                $this->indexFile($parser, $file);
            }
        }
    }

    private function indexFile(Parser $parser, string $file): void
    {
        try {
            $stmts = $parser->parse((string) \file_get_contents($file)) ?? [];
        } catch (\Throwable) {
            return;
        }

        $stmts = (new NodeTraverser(new NameResolver()))->traverse($stmts);

        foreach ((new NodeFinder())->findInstanceOf($stmts, ClassLike::class) as $node) {
            if (!$node instanceof Class_ && !$node instanceof Trait_ && !$node instanceof Enum_) {
                continue;
            }

            $traits = [];
            $adaptsTraits = false;
            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof TraitUse) {
                    continue;
                }

                foreach ($stmt->traits as $trait) {
                    $traits[] = 'trait:' . self::key($trait->toString());
                }
                $stmt->adaptations === [] or $adaptsTraits = true;
            }

            $parent = $node instanceof Class_ && $node->extends !== null ? self::key($node->extends->toString()) : null;
            $parent === null or $this->extended[$parent] = true;

            $name = $node->namespacedName?->toString();
            $key = match (true) {
                $node instanceof Trait_ && $name !== null => 'trait:' . self::key($name),
                $name !== null => self::key($name),
                default => 'anonymous:' . $file . ':' . $node->getStartLine(),
            };

            $this->types[$key] = [
                'parent' => $parent,
                'classLevelTest' => $node instanceof Class_ && $name !== null && $this->hasTestAttribute($node),
                'isClass' => $node instanceof Class_,
                'traits' => $traits,
                'adaptsTraits' => $adaptsTraits,
            ];
        }
    }

    private function hasTestAttribute(Class_ $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                if (self::key($attr->name->toString()) === self::TEST) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(string $path): iterable
    {
        if (\is_file($path)) {
            yield $path;

            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
