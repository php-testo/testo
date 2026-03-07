<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\StringType;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\AssertException;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Attribute\AssertMethod;

/**
 * Assertion utilities for string data type.
 *
 * @internal
 */
class AssertString implements StringType
{
    public function __construct(
        private readonly string $value,
        private readonly AssertionComposite $parent,
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
     * @throws AssertException when the assertion fails.
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
     * @throws AssertException when the assertion fails.
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
}
