<?php

declare(strict_types=1);

namespace Testo\Skip\Internal;

use Testo\Common\Reflection;
use Testo\Core\Definition\TestDefinition;
use Testo\Requires;
use Testo\Skip;

/**
 * Decides whether a test is skipped, by {@see Skip} or by an unmet {@see Requires}, and why.
 *
 * The decision is kept per test definition, so a requirement is checked once even though both
 * {@see SkipLocatorInterceptor} and {@see SkipInterceptor} ask, and the interceptor still decides
 * on its own when the locator is not registered. A requirement that throws is kept as thrown: every
 * later ask rethrows it, inside the test's pipeline, without calling the requirement again.
 *
 * @internal
 * @psalm-internal Testo\Skip
 */
final class SkipDecision
{
    /** @var \WeakMap<TestDefinition, non-empty-string|false|\Throwable>|null */
    private static ?\WeakMap $decisions = null;

    /** @var \WeakMap<\ReflectionClass, non-empty-string|false|\Throwable>|null */
    private static ?\WeakMap $classVerdicts = null;

    /**
     * The skip message without the test id it opens with, or `null` when the test runs.
     *
     * @return non-empty-string|null
     * @throws \Throwable What a requirement threw.
     */
    public static function of(TestDefinition $test, ?\ReflectionClass $class): ?string
    {
        self::$decisions ??= new \WeakMap();
        isset(self::$decisions[$test]) or self::$decisions[$test] = self::attempt(
            static fn(): ?string => self::decide($test, $class),
        );

        return self::unwrap(self::$decisions[$test]);
    }

    /**
     * @return non-empty-string|null
     */
    private static function decide(TestDefinition $test, ?\ReflectionClass $class): ?string
    {
        $skip = self::skip($test, $class);
        if ($skip !== null) {
            return self::compose('is skipped via #[Skip]', $skip->reason);
        }

        return self::classVerdict($class) ?? self::firstUnmet(
            Reflection::fetchFunctionAttributes($test->reflection, attributeClass: Requires::class),
            $class,
        );
    }

    /**
     * The class-wide requirements, parents and traits included, are checked once for the whole
     * case rather than once per test: a probe of an external service is the typical one.
     *
     * @return non-empty-string|null
     */
    private static function classVerdict(?\ReflectionClass $class): ?string
    {
        if ($class === null) {
            return null;
        }

        self::$classVerdicts ??= new \WeakMap();
        isset(self::$classVerdicts[$class]) or self::$classVerdicts[$class] = self::attempt(
            static fn(): ?string => self::firstUnmet(
                Reflection::fetchClassAttributes($class, attributeClass: Requires::class),
                $class,
            ),
        );

        return self::unwrap(self::$classVerdicts[$class]);
    }

    /**
     * @param list<\ReflectionAttribute<Requires>> $attributes
     * @return non-empty-string|null
     */
    private static function firstUnmet(array $attributes, ?\ReflectionClass $class): ?string
    {
        foreach ($attributes as $attribute) {
            $requires = $attribute->newInstance();
            $target = self::target($requires->condition, $class);
            if (!self::holds($target)) {
                $name = self::name($target);
                return self::compose("is skipped: requirement {$name}() is not met", $requires->reason);
            }
        }

        return null;
    }

    /**
     * `limit: 1` stops at the nearest declaration, so an overriding function's own `#[Skip]` is
     * taken over the one it inherits. The class is consulted only when the function carries none
     * of its own.
     */
    private static function skip(TestDefinition $test, ?\ReflectionClass $class): ?Skip
    {
        $attributes = Reflection::fetchFunctionAttributes($test->reflection, attributeClass: Skip::class, limit: 1);

        $attributes === [] && $class !== null and $attributes = Reflection::fetchClassAttributes(
            $class,
            attributeClass: Skip::class,
            limit: 1,
        );

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }

    /**
     * The callable a condition names. A bare name is a static method of the test class when it has
     * one, as with `#[DataProvider('name')]`, and a function otherwise; `self` and `static` stand
     * for the test class.
     *
     * @param \Closure|string|array<array-key, mixed> $condition
     * @return \Closure|non-empty-string|array{non-empty-string, non-empty-string}
     */
    private static function target(\Closure|string|array $condition, ?\ReflectionClass $class): \Closure|string|array
    {
        \is_string($condition) && \str_contains($condition, '::') and $condition = \explode('::', $condition, 2);

        if (\is_string($condition)) {
            $condition === '' and throw new \InvalidArgumentException('#[Requires] condition is empty.');
            return $class?->hasMethod($condition) && $class->getMethod($condition)->isStatic()
                ? [$class->getName(), $condition]
                : $condition;
        }

        if ($condition instanceof \Closure) {
            return $condition;
        }

        \array_is_list($condition) && \count($condition) === 2
        && \is_string($condition[0]) && $condition[0] !== ''
        && \is_string($condition[1]) && $condition[1] !== ''
        or throw new \InvalidArgumentException(
            '#[Requires] condition array must be [class, method], got ' . \json_encode($condition) . '.',
        );

        $class !== null && \in_array(\strtolower($condition[0]), ['self', 'static'], true)
        and $condition[0] = $class->getName();

        return $condition;
    }

    /**
     * Static methods go through reflection rather than a call, which would reject a private helper
     * of the test class from out here.
     *
     * Anything but a bool is rejected rather than cast: `'php_sapi_name'` returns `'cli'`, and a
     * cast would take that for a met requirement.
     *
     * @param \Closure|non-empty-string|array{non-empty-string, non-empty-string} $target
     */
    private static function holds(\Closure|string|array $target): bool
    {
        $result = \is_array($target)
            ? (new \ReflectionMethod($target[0], $target[1]))->invoke(null)
            : $target();

        \is_bool($result) or throw new \UnexpectedValueException(\sprintf(
            'Requirement %s() must return bool, %s returned.',
            self::name($target),
            \get_debug_type($result),
        ));

        return $result;
    }

    /**
     * @param \Closure|non-empty-string|array{non-empty-string, non-empty-string} $target
     */
    private static function name(\Closure|string|array $target): string
    {
        if (\is_array($target)) {
            return "{$target[0]}::{$target[1]}";
        }

        if (\is_string($target)) {
            return $target;
        }

        $function = new \ReflectionFunction($target);
        $scope = $function->getClosureScopeClass();

        return $function->isClosure() && \str_contains($function->getName(), '{closure')
            ? '{closure}'
            : ($scope === null ? $function->getName() : "{$scope->getName()}::{$function->getName()}");
    }

    /**
     * @param \Closure(): (non-empty-string|null) $decide
     * @return non-empty-string|false|\Throwable
     */
    private static function attempt(\Closure $decide): string|false|\Throwable
    {
        try {
            return $decide() ?? false;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * @param non-empty-string|false|\Throwable $kept
     * @return non-empty-string|null
     */
    private static function unwrap(string|false|\Throwable $kept): ?string
    {
        $kept instanceof \Throwable and throw $kept;

        return $kept ?: null;
    }

    /**
     * @return non-empty-string
     */
    private static function compose(string $message, string $reason): string
    {
        return $reason === '' ? $message : "{$message} ==> {$reason}";
    }
}
