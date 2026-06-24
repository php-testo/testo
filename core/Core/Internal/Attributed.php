<?php

declare(strict_types=1);

namespace Testo\Core\Internal;

/**
 * Trait providing attribute management functionality.
 */
trait Attributed
{
    use CloneWith;

    /** @var array<non-empty-string, mixed> */
    public readonly array $attributes;

    /**
     * @return array<non-empty-string, mixed> Attributes derived from the context.
     * @psalm-mutation-free
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @template T
     * @template D
     * @param non-empty-string|class-string<T> $name
     * @param D $default
     * @return ($name is class-string<T> ? T|D : mixed|D)
     * @psalm-mutation-free
     */
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    /**
     * Note that if the key contains a class name, the value must be an instance of that class.
     *
     * @param non-empty-string $name
     * @psalm-immutable
     */
    public function withAttribute(string $name, mixed $value): static
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;
        return $this->cloneWith('attributes', $attributes);
    }

    /**
     * Set multiple attributes at once.
     *
     * The new attributes will be merged with existing ones.
     *
     * @param non-empty-array<non-empty-string, mixed> $values
     * @psalm-immutable
     */
    public function withAttributes(array $values): static
    {
        $attributes = \array_merge($this->attributes, $values);
        return $this->cloneWith('attributes', $attributes);
    }

    /**
     * @param non-empty-string $name
     * @psalm-immutable
     */
    public function withoutAttribute(string $name): static
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);
        return $this->cloneWith('attributes', $attributes);
    }
}
