<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node\Stmt\Class_;
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
    ) {}

    public function isTestCase(Class_ $class): bool
    {
        return $this->extendsDirectly($class) || $this->inheritsTestCase($class);
    }

    public function extendsDirectly(Class_ $class): bool
    {
        return $class->extends !== null && $this->nodeNameResolver->isName($class->extends, self::TEST_CASE);
    }

    private function inheritsTestCase(Class_ $class): bool
    {
        $name = $class->namespacedName?->toString();
        if ($name === null || !$this->reflectionProvider->hasClass($name)) {
            return false;
        }

        return $this->reflectionProvider->getClass($name)->isSubclassOf(self::TEST_CASE);
    }
}
