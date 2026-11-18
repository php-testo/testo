<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Stmt\Class_;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeNameResolver\NodeNameResolver;

/**
 * Tells whether a class is a PHPUnit test class: one that extends `\PHPUnit\Framework\TestCase`
 * directly or through any number of project or vendor base classes.
 *
 * The direct `extends` is read off the AST; the ancestry is read from PHPStan's reflection, which
 * loads a class once per process from its source before it is rewritten, so the answer stays the same
 * after another rule has already detached the class or its base from `TestCase` in the same run.
 * That holds for a serial run only: a parallel worker may load a base another worker has already
 * written back, which is why the conversion set disables parallel processing.
 *
 * @internal
 */
final class PhpunitTestCaseClass
{
    public const TEST_CASE = 'PHPUnit\\Framework\\TestCase';

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly ProcessedClasses $processedClasses,
    ) {}

    /**
     * The nearest ancestor declared outside the processed paths through which the class reaches
     * `TestCase`, such as a framework's test case from vendor. No rule detaches that base, so it keeps
     * the class on PHPUnit after the run.
     *
     * @return array{name: string, direct: bool}|null The base, and whether it is the direct parent.
     */
    public function phpunitBaseFromVendor(Class_ $class): ?array
    {
        $base = $this->extendsDirectly($class) ? null : $this->base($class);
        if ($base === null) {
            return null;
        }

        $direct = true;
        foreach ([$base, ...$base->getParents()] as $parent) {
            if ($parent->getName() === self::TEST_CASE) {
                return null;
            }

            if (!$this->processedClasses->declares($parent->getName())) {
                return $parent->isSubclassOf(self::TEST_CASE) ? ['name' => $parent->getName(), 'direct' => $direct] : null;
            }

            $direct = false;
        }

        return null;
    }

    public function isTestCase(Class_ $class): bool
    {
        return $this->extendsDirectly($class) || $this->inheritsTestCase($class);
    }

    public function extendsDirectly(Class_ $class): bool
    {
        return $class->extends !== null && $this->nodeNameResolver->isName($class->extends, self::TEST_CASE);
    }

    /**
     * The class's direct base as loaded from source, reflected from the name it extends: an anonymous
     * class has no name of its own to reflect.
     *
     * @return ClassReflection|null Null when the class extends nothing or its base cannot be resolved.
     */
    public function base(Class_ $class): ?ClassReflection
    {
        $name = $class->extends === null ? null : $this->nodeNameResolver->getName($class->extends);

        return $name !== null && $this->reflectionProvider->hasClass($name)
            ? $this->reflectionProvider->getClass($name)
            : null;
    }

    private function inheritsTestCase(Class_ $class): bool
    {
        return $this->base($class)?->isSubclassOf(self::TEST_CASE) ?? false;
    }
}
