<?php

declare(strict_types=1);

namespace Testo\Bridge\Rector\Internal;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * Recognises a PHPUnit assertion call in any of its spellings: `$this->assertX()`, `self::assertX()`,
 * `static::assertX()`, `parent::assertX()`, `Assert::assertX()` and `TestCase::assertX()` with
 * PHPUnit's classes, and the global function `PHPUnit\Framework\assertX()`.
 *
 * Only a call inside a class counts, which is where a test method or a `static` data provider
 * lives; a call in a free function or at namespace level is left alone.
 * A method call must resolve to PHPUnit's `Assert`/`TestCase` declaration: an unresolved ancestry,
 * a magic method, or a user declaration with the same name stays untouched. In a trait a call to a
 * method PHPUnit declares counts unless the trait implements, intercepts, or requires a
 * value-returning version of it.
 *
 * @internal
 */
final class PhpunitAssertionCall
{
    public const NODE_TYPES = [MethodCall::class, StaticCall::class, FuncCall::class];
    private const FUNCTION_NAMESPACE = 'PHPUnit\\Framework\\';
    private const ASSERT = 'PHPUnit\\Framework\\Assert';
    private const TEST_CASE = 'PHPUnit\\Framework\\TestCase';
    private const RESTATED_RETURN_TYPES = ['void', 'never'];

    public function __construct(
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly ReflectionProvider $reflectionProvider,
    ) {}

    /**
     * @return non-empty-string|null The assertion's method name, e.g. `assertSame`.
     */
    public function name(Node $node): ?string
    {
        $name = match (true) {
            $node instanceof MethodCall => $this->nodeNameResolver->isName($node->var, 'this')
                ? $this->nodeNameResolver->getName($node->name)
                : null,
            $node instanceof StaticCall => $this->nodeNameResolver->isNames(
                $node->class,
                ['self', 'static', 'parent', self::ASSERT, self::TEST_CASE],
            ) ? $this->nodeNameResolver->getName($node->name) : null,
            $node instanceof FuncCall => $this->functionName($node),
            default => null,
        };

        if ($name === null || $name === '' || !$this->isInClassScope($node)) {
            return null;
        }

        if ($node instanceof FuncCall) {
            return $name;
        }

        if ($node instanceof StaticCall && $this->nodeNameResolver->isNames($node->class, [self::ASSERT, self::TEST_CASE])) {
            $class = (string) $this->nodeNameResolver->getName($node->class);

            return $this->reflectionProvider->hasClass($class)
                ? $this->declaredByPhpunit($this->reflectionProvider->getClass($class), $name)
                : null;
        }

        $scope = $node->getAttribute(AttributeKey::SCOPE);
        \assert($scope instanceof Scope);

        $viaParent = $node instanceof StaticCall && $this->nodeNameResolver->isName($node->class, 'parent');

        return $this->phpunitMethodName($scope, $name, $viaParent);
    }

    /**
     * The call's arguments in PHPUnit's parameter order ({@see PositionalArgs::bind()}). Named ones
     * are bound through the reflection of `Assert`, so the installed PHPUnit version decides the
     * names; a variadic declaration or a missing `Assert` leaves them unresolved.
     *
     * @param non-empty-string $method The assertion's method name, as returned by {@see name()}.
     * @return list<Arg>|null
     */
    public function positionalArgs(MethodCall|StaticCall|FuncCall $call, string $method): ?array
    {
        return PositionalArgs::bind($call->args, fn(): ?array => $this->parameterNames($method));
    }

    /**
     * Whether a trait's abstract method only restates a PHPUnit assertion, so that it can go once the
     * calls it backs are converted. A trait that intercepts calls keeps its calls unconverted, and the
     * declaration with them.
     */
    public function restatesPhpunitAssertion(Trait_ $trait, ClassMethod $method): bool
    {
        $name = $method->name->toLowerString();
        $type = $method->returnType === null ? null : \strtolower((string) $this->nodeNameResolver->getName($method->returnType));

        return $trait->getMethod('__call') === null
            && $trait->getMethod('__callStatic') === null
            && $method->isAbstract()
            && (\str_starts_with($name, 'assert') || $name === 'fail')
            && ($type === null || \in_array($type, self::RESTATED_RETURN_TYPES, true))
            && $this->reflectionProvider->hasClass(self::ASSERT)
            && $this->reflectionProvider->getClass(self::ASSERT)->hasNativeMethod($name);
    }

    /**
     * @param non-empty-string $name
     * @return non-empty-string|null
     */
    private function phpunitMethodName(Scope $scope, string $name, bool $viaParent): ?string
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return null;
        }

        if ($class->isTrait()) {
            return $viaParent ? null : $this->traitPhpunitMethodName($class, $name);
        }

        # `parent::` skips the class's own declaration and resolves from its parent on.
        $resolve = fn(ClassReflection $class): ?string => $viaParent
            ? ($class->getParentClass() === null ? null : $this->declaredByPhpunit($class->getParentClass(), $name))
            : $this->declaredByPhpunit($class, $name);

        $owned = $resolve($class);
        if ($owned !== null) {
            return $owned;
        }

        # The scope is refreshed from the rewritten node, so a class another rule has already detached
        # from `TestCase` no longer reaches PHPUnit there; PHPStan's reflection keeps it as loaded from
        # source. It is looked up by name, so it only counts when it comes from the same file.
        $file = $class->getFileName();
        if ($file === null || !$this->reflectionProvider->hasClass($class->getName())) {
            return null;
        }

        $source = $this->reflectionProvider->getClass($class->getName());

        return $source->getFileName() === $file ? $resolve($source) : null;
    }

    /**
     * @param non-empty-string $name
     * @return non-empty-string|null The method's declared name.
     */
    private function declaredByPhpunit(ClassReflection $class, string $name): ?string
    {
        if (!$class->hasNativeMethod($name)) {
            return null;
        }

        $method = $class->getNativeMethod($name);
        return \in_array($method->getDeclaringClass()->getName(), [self::ASSERT, self::TEST_CASE], true)
            ? $method->getName()
            : null;
    }

    /**
     * A trait cannot tell which classes use it: a method PHPUnit declares is taken as PHPUnit's unless
     * the trait implements or intercepts it. An abstract declaration that is untyped, `void` or `never`
     * only restates PHPUnit's signature, while one that returns a value requires a custom implementation.
     *
     * @param non-empty-string $name
     * @return non-empty-string|null The method's declared name.
     */
    private function traitPhpunitMethodName(ClassReflection $trait, string $name): ?string
    {
        if ($trait->hasNativeMethod('__call') || $trait->hasNativeMethod('__callStatic')
            || !$this->reflectionProvider->hasClass(self::TEST_CASE)
        ) {
            return null;
        }

        $declared = $this->declaredByPhpunit($this->reflectionProvider->getClass(self::TEST_CASE), $name);
        if ($declared === null || !$trait->hasNativeMethod($name)) {
            return $declared;
        }

        $method = $trait->getNativeReflection()->getMethod($name);
        $type = $method->getReturnType();

        return $method->isAbstract() && ($type === null || \in_array((string) $type, self::RESTATED_RETURN_TYPES, true))
            ? $declared
            : null;
    }

    /**
     * @param non-empty-string $method
     * @return list<string>|null Null when `Assert` or the method is unknown, or the method is variadic.
     */
    private function parameterNames(string $method): ?array
    {
        if (!$this->reflectionProvider->hasClass(self::ASSERT)) {
            return null;
        }

        $class = $this->reflectionProvider->getClass(self::ASSERT);
        if (!$class->hasNativeMethod($method)) {
            return null;
        }

        $names = [];
        foreach ($class->getNativeMethod($method)->getOnlyVariant()->getParameters() as $parameter) {
            if ($parameter->isVariadic()) {
                return null;
            }

            $names[] = $parameter->getName();
        }

        return $names;
    }

    private function functionName(FuncCall $call): ?string
    {
        $name = $this->nodeNameResolver->getName($call);
        if ($name === null || !\str_starts_with(\ltrim($name, '\\'), self::FUNCTION_NAMESPACE)) {
            return null;
        }

        return \substr(\ltrim($name, '\\'), \strlen(self::FUNCTION_NAMESPACE));
    }

    private function isInClassScope(Node $node): bool
    {
        $scope = $node->getAttribute(AttributeKey::SCOPE);

        return $scope instanceof Scope && $scope->isInClass();
    }
}
