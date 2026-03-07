<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\ObjectType;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Attribute\AssertMethod;

/**
 * Assertion utilities for objects.
 *
 * @internal
 */
final class AssertObject implements ObjectType
{
    public function __construct(
        private readonly object $value,
        private readonly AssertionComposite $parent,
    ) {}

    /**
     * @template ValueType
     *
     * @param ValueType $value The value to be asserted as object.
     * @throws AssertionException when the value is not an object.
     *
     * @psalm-assert object $value
     * @phpstan-assert object $value
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_object($value) or StaticState::typeFail('object', $value);

        $parent = StaticState::typeSuccess('object', $value);
        return new self($value, $parent);
    }

    #[AssertMethod]
    #[\Override]
    public function instanceOf(string $expected, string $message = ''): static
    {
        $str = "is instance of `{$expected}`";
        $this->value instanceof $expected
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'got `' . $this->value::class . '` instead', $message);
        return $this;
    }

    #[AssertMethod]
    #[\Override]
    public function hasProperty(string $propertyName, string $message = ''): static
    {
        $str = "has property `{$propertyName}`";
        \property_exists($this->value, $propertyName)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'does not have property `' . $propertyName . '`', $message);
        return $this;
    }
}
