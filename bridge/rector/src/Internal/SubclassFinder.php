<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;

/**
 * Tells whether a class is extended by another class within the paths Rector processes.
 *
 * Reflection cannot list the subclasses of a class, so the processed files are parsed once, on the
 * first question, and their `extends` clauses indexed. A subclass outside the processed paths stays
 * unknown.
 *
 * @internal
 */
final class SubclassFinder
{
    /** @var list<string>|null The processed paths the index was built from. */
    private ?array $indexedPaths = null;

    /** @var array<lowercase-string, true> Lowercased names of the extended classes. */
    private array $extended = [];

    public function hasSubclass(string $class): bool
    {
        $paths = SimpleParameterProvider::provideArrayParameter(Option::SOURCE);
        $paths === $this->indexedPaths or $this->index($paths);

        return isset($this->extended[\strtolower(\ltrim($class, '\\'))]);
    }

    /**
     * @param list<string> $paths
     */
    private function index(array $paths): void
    {
        $this->indexedPaths = $paths;
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

        $traverser = new NodeTraverser(new NameResolver());
        $stmts = $traverser->traverse($stmts);

        foreach ((new NodeFinder())->findInstanceOf($stmts, Class_::class) as $class) {
            $class->extends === null or $this->extended[\strtolower($class->extends->toString())] = true;
        }
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
