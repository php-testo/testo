<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\CallableType;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Common\Attribute\AssertMethod;

/**
 * Assertion utilities for callables.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertCallable implements CallableType
{
    /**
     * @param callable $value The asserted callable as given, so a failure shows the original value.
     * @param \ReflectionFunctionAbstract $reflection The function or method the callable points to.
     */
    public function __construct(
        private mixed $value,
        private \ReflectionFunctionAbstract $reflection,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is callable and return an AssertCallable instance.
     *
     * The check runs in the scope of this class, so private and protected methods of other classes fail.
     *
     * @param mixed $value The value to be asserted as callable.
     * @throws AssertionException when the value is not callable.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_callable($value) or StaticState::typeFail('callable', $value);

        $parent = StaticState::typeSuccess('callable', $value);
        return new self($value, self::reflect($value), $parent);
    }

    #[AssertMethod]
    #[\Override]
    public function isStatic(string $message = ''): static
    {
        $this->bindsNoThis()
            ? $this->parent->success('is static', $message)
            : throw $this->parent->fail('is static', $this->name() . ' is not static', $message);
        return $this;
    }

    #[AssertMethod]
    #[\Override]
    public function notStatic(string $message = ''): static
    {
        $this->bindsNoThis()
            ? throw $this->parent->fail('is not static', $this->name() . ' is static', $message)
            : $this->parent->success('is not static', $message);
        return $this;
    }

    #[AssertMethod]
    #[\Override]
    public function hasReturnType(string $type, string $message = ''): static
    {
        $reflection = $this->reflection;
        $declared = $reflection->getReturnType() ?? $reflection->getTentativeReturnType();

        $str = "has return type `{$type}`";
        $declared !== null && self::normalizeType((string) $declared) === self::normalizeType($type)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail(
                $str,
                $declared === null
                    ? $this->name() . ' declares no return type'
                    : $this->name() . " declares `{$declared}`",
                $message,
            );
        return $this;
    }

    /**
     * Reflection of the function or method the callable points to. A form the direct lookup cannot
     * resolve, such as a method served by `__call()` or a `parent::method` array, goes through a closure.
     */
    private static function reflect(callable $value): \ReflectionFunctionAbstract
    {
        try {
            return match (true) {
                $value instanceof \Closure => new \ReflectionFunction($value),
                \is_object($value) => new \ReflectionMethod($value, '__invoke'),
                \is_array($value) => new \ReflectionMethod($value[0], $value[1]),
                \str_contains($value, '::') => new \ReflectionMethod(...\explode('::', $value, 2)),
                default => new \ReflectionFunction($value),
            };
        } catch (\ReflectionException) {
            return new \ReflectionFunction(\Closure::fromCallable($value));
        }
    }

    /**
     * Canonical spelling of a type declaration: no whitespace or leading backslashes, lower case,
     * `?T` as `T|null`, union and intersection members sorted.
     */
    private static function normalizeType(string $type): string
    {
        $type = (string) \preg_replace('/\s+/', '', $type);
        \str_starts_with($type, '?') and $type = \substr($type, 1) . '|null';

        $union = \explode('|', $type);
        $parts = [];
        foreach ($union as $part) {
            $members = \array_map(
                static fn(string $member): string => \strtolower(\ltrim($member, '\\')),
                \explode('&', \trim($part, '()')),
            );
            \sort($members);
            $intersection = \implode('&', $members);
            $parts[] = \count($members) > 1 && \count($union) > 1 ? "({$intersection})" : $intersection;
        }
        \sort($parts);

        return \implode('|', $parts);
    }

    /**
     * `ReflectionFunction::isStatic()` is false for a plain function, which has no `$this` to bind
     * either, so a function outside any class scope that is not an anonymous closure counts as static.
     */
    private function bindsNoThis(): bool
    {
        $reflection = $this->reflection;
        return $reflection->isStatic()
            || $reflection instanceof \ReflectionFunction
            && $reflection->getClosureScopeClass() === null
            && !\str_contains($reflection->name, '{closure');
    }

    private function name(): string
    {
        $reflection = $this->reflection;
        if (\str_contains($reflection->name, '{closure')) {
            return 'the closure';
        }

        $class = $reflection instanceof \ReflectionMethod
            ? $reflection->class
            : $reflection->getClosureScopeClass()?->name;
        return '`' . ($class === null ? '' : $class . '::') . $reflection->name . '()`';
    }
}
