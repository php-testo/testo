<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion\Traits;

use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Attribute\AssertMethod;

/**
 * Contains assertion methods for iterable values.
 *
 * @property iterable $value
 * @property AssertionComposite $parent
 */
trait IterableTrait
{
    #[AssertMethod]
    #[\Override]
    public function notEmpty(string $message = ''): static
    {
        $str = 'is not empty';
        foreach ($this->value as $_) {
            $this->parent->success($str);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: 'it is empty',
            context: $message,
        );
    }

    #[AssertMethod]
    #[\Override]
    public function contains(mixed $needle, string $message = ''): static
    {
        $str = 'contains `' . Support::stringify($needle) . '`';
        foreach ($this->value as $item) {
            if ($item === $needle) {
                $this->parent->success($str);
                return $this;
            }
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: 'element not found',
            context: $message,
        );
    }

    #[\Override]
    public function every(callable $callback, string $message = ''): static
    {
        $str = 'every element matches callback';
        foreach ($this->value as $key => $item) {
            if (! $callback($item)) {
                throw $this->parent->fail(
                    assertion: $str,
                    reason: "element at `{$key}` failed: `" . Support::stringify($item) . '`',
                    context: $message,
                );
            }
        }

        $this->parent->success($str);

        return $this;
    }

    #[AssertMethod]
    #[\Override]
    public function sameSizeAs(iterable $expected, string $message = ''): static
    {
        $str = 'has same size as `' . Support::stringify($expected) . '`';
        $countThis = self::countIterable($this->value);
        $countExpected = self::countIterable($expected);
        if ($countThis === $countExpected) {
            $this->parent->success($str);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "got size `{$countThis}` instead of `{$countExpected}`",
            context: $message,
        );
    }

    #[AssertMethod]
    #[\Override]
    public function allOf(string $type, string $message = ''): static
    {
        $type = \strtolower($type);
        $type = match ($type) {
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            default => $type,
        };

        $str = "has all elements of type `{$type}`";
        foreach ($this->value as $element) {
            $actualType = \strtolower(\get_debug_type($element));
            $actualType === $type or throw $this->parent->fail(
                assertion: $str,
                reason: "found element of type `{$actualType}`",
                context: $message,
            );
        }

        $this->parent->success($str);
        return $this;
    }

    #[AssertMethod]
    #[\Override]
    public function hasCount(int $expected): static
    {
        $count = self::countIterable($this->value);
        $str = "has count `$expected`";
        if ($count === $expected) {
            $this->parent->success($str);
            return $this;
        }

        throw $this->parent->fail(
            assertion: $str,
            reason: "got `$count` instead",
        );
    }

    /**
     * Counts the number of elements in the given iterable.
     */
    private static function countIterable(iterable $value): int
    {
        // if Countable
        if (\is_array($value) || $value instanceof \Countable) {
            return \count($value);
        }

        // if Traversable
        $count = 0;
        foreach ($value as $_) {
            $count++;
        }

        return $count;
    }
}
