<?php

declare(strict_types=1);

namespace Tests\Assert\Fixture;

/**
 * Methods of every shape the callable assertions reflect on.
 */
final class CallableSubject
{
    public static function staticMethod(): ?string
    {
        return null;
    }

    public function instanceMethod(): static
    {
        return $this;
    }

    public function intersection(): \Countable&\ArrayAccess
    {
        return new \ArrayObject();
    }

    /**
     * A closure not declared `static`: it uses `$this`, so code style fixers keep it that way.
     */
    public function boundClosure(): \Closure
    {
        return fn(): self => $this;
    }

    public function untyped($value)
    {
        return $value;
    }

    public function __invoke(): int|string|null
    {
        return null;
    }

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }
}
