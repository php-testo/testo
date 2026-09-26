<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\StringType;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Common\Attribute\AssertMethod;

/**
 * Assertion utilities for string data type.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertString implements StringType
{
    public function __construct(
        private string $value,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is a string and return an AssertString instance.
     *
     * @param mixed $value The value to be asserted as string.
     * @return self An instance of AssertString.
     *
     * @throws AssertionException when the value is not a string.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_string($value) or StaticState::typeFail('string', $value);

        $parent = StaticState::typeSuccess('string', $value);
        return new self($value, $parent);
    }

    /**
     * Asserts that the string contains the given substring.
     *
     * @param non-empty-string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function contains(string $needle, string $message = ''): static
    {
        $str = 'contains "' . $needle . '"';
        \str_contains($this->value, $needle)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the substring is not found', $message);
        return $this;
    }

    /**
     * Asserts that the string does not contain the given substring.
     *
     * @param non-empty-string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function notContains(string $needle, string $message = ''): static
    {
        $str = 'does not contain "' . $needle . '"';
        !\str_contains($this->value, $needle)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the substring is found', $message);
        return $this;
    }

    /**
     * Asserts that the string starts with the given prefix.
     *
     * @param string $prefix Expected beginning of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function startsWith(string $prefix, string $message = ''): static
    {
        $str = 'starts with "' . $prefix . '"';
        \str_starts_with($this->value, $prefix)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the string starts differently', $message);
        return $this;
    }

    /**
     * Asserts that the string ends with the given suffix.
     *
     * @param string $suffix Expected end of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function endsWith(string $suffix, string $message = ''): static
    {
        $str = 'ends with "' . $suffix . '"';
        \str_ends_with($this->value, $suffix)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the string ends differently', $message);
        return $this;
    }
}
