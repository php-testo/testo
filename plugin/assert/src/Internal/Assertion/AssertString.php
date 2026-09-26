<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\StringType;
use Testo\Assert\Internal\Pattern;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\Internal\StringNormalizer;
use Testo\Assert\Internal\Support;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;
use Testo\Assert\State\Assertion\ComparisonFailure;
use Testo\Common\Attribute\AssertMethod;

/**
 * Assertion utilities for string data type.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertString implements StringType
{
    /** What the substring checks compare against: the value normalized by every active mode. */
    private string $subject;

    /** What the patterns run against: the value normalized by every active mode but the case one. */
    private string $patternSubject;

    /**
     * @param string $value The asserted value as given; failure messages show it unchanged.
     */
    public function __construct(
        private string $value,
        private AssertionComposite $parent,
        private StringNormalizer $normalizer = new StringNormalizer(),
    ) {
        $this->subject = $normalizer->normalize($value);
        $this->patternSubject = $normalizer->normalize($value, withCase: false);
    }

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

    #[\Override]
    public function ignoringCase(): static
    {
        return new self($this->value, $this->parent, $this->normalizer->withIgnoreCase());
    }

    #[\Override]
    public function ignoringLineEndings(): static
    {
        return new self($this->value, $this->parent, $this->normalizer->withIgnoreLineEndings());
    }

    #[\Override]
    public function ignoringWhitespace(bool $lineBreaks = false): static
    {
        return new self($this->value, $this->parent, $this->normalizer->withIgnoreWhitespace($lineBreaks));
    }

    #[\Override]
    public function ignoringBlankLines(): static
    {
        return new self($this->value, $this->parent, $this->normalizer->withIgnoreBlankLines());
    }

    #[\Override]
    public function ignoringAnsi(): static
    {
        return new self($this->value, $this->parent, $this->normalizer->withIgnoreAnsi());
    }

    /**
     * Asserts that the string is identical to the expected one.
     *
     * @param string $expected The expected string.
     * @param string $message Optional message for the assertion.
     * @throws ComparisonFailure when the assertion fails; it compares the normalized strings.
     */
    #[AssertMethod]
    #[\Override]
    public function same(string $expected, string $message = ''): static
    {
        $str = 'is the same as ' . $this->describe($expected);
        $normalized = $this->normalizer->normalize($expected);
        $this->subject === $normalized
            ? $this->parent->success($str, $message)
            : throw $this->comparisonFailure($normalized, $str, $message, 'expected ' . Support::stringify($expected));
        return $this;
    }

    /**
     * Asserts that the string differs from the given one.
     *
     * @param string $expected The string the value must differ from.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     */
    #[AssertMethod]
    #[\Override]
    public function notSame(string $expected, string $message = ''): static
    {
        $str = 'is not the same as ' . $this->describe($expected);
        $this->subject !== $this->normalizer->normalize($expected)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the strings are the same', $message);
        return $this;
    }

    /**
     * Asserts that the string contains the given substring.
     *
     * @param non-empty-string $needle Substring to search for.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the needle is empty only after normalization.
     */
    #[AssertMethod]
    #[\Override]
    public function contains(string $needle, string $message = ''): static
    {
        $str = 'contains ' . $this->describe($needle);
        \str_contains($this->subject, $this->argument($needle, 'contains'))
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
     * @throws \InvalidArgumentException when the needle is empty only after normalization.
     */
    #[AssertMethod]
    #[\Override]
    public function notContains(string $needle, string $message = ''): static
    {
        $str = 'does not contain ' . $this->describe($needle);
        !\str_contains($this->subject, $this->argument($needle, 'notContains'))
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the substring is found', $message);
        return $this;
    }

    /**
     * Asserts that the string starts with the given prefix.
     *
     * @param non-empty-string $prefix Expected beginning of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the prefix is empty only after normalization.
     */
    #[AssertMethod]
    #[\Override]
    public function startsWith(string $prefix, string $message = ''): static
    {
        $str = 'starts with ' . $this->describe($prefix);
        \str_starts_with($this->subject, $this->argument($prefix, 'startsWith'))
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the string starts differently', $message);
        return $this;
    }

    /**
     * Asserts that the string ends with the given suffix.
     *
     * @param non-empty-string $suffix Expected end of the string.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the suffix is empty only after normalization.
     */
    #[AssertMethod]
    #[\Override]
    public function endsWith(string $suffix, string $message = ''): static
    {
        $str = 'ends with ' . $this->describe($suffix);
        \str_ends_with($this->subject, $this->argument($suffix, 'endsWith'))
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the string ends differently', $message);
        return $this;
    }

    /**
     * Asserts that the string matches the given PCRE pattern.
     *
     * @param string $pattern Full PCRE pattern with delimiters and flags.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the pattern is invalid.
     */
    #[AssertMethod]
    #[\Override]
    public function matchesPattern(string $pattern, string $message = ''): static
    {
        $str = 'matches pattern ' . $pattern . $this->normalizer->describe(withCase: false);
        Pattern::matches($pattern, $this->patternSubject)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the pattern does not match', $message);
        return $this;
    }

    /**
     * Asserts that the string does not match the given PCRE pattern.
     *
     * @param string $pattern Full PCRE pattern with delimiters and flags.
     * @param string $message Optional message for the assertion.
     * @throws AssertionException when the assertion fails.
     * @throws \InvalidArgumentException when the pattern is invalid.
     */
    #[AssertMethod]
    #[\Override]
    public function notMatchesPattern(string $pattern, string $message = ''): static
    {
        $str = 'does not match pattern ' . $pattern . $this->normalizer->describe(withCase: false);
        !Pattern::matches($pattern, $this->patternSubject)
            ? $this->parent->success($str, $message)
            : throw $this->parent->fail($str, 'the pattern matches', $message);
        return $this;
    }

    /**
     * The check argument as given, quoted, followed by the active modes.
     */
    private function describe(string $argument): string
    {
        return '"' . Support::escapeControlChars($argument) . '"' . $this->normalizer->describe();
    }

    /**
     * A failure carrying the normalized strings, so a diff shows only what made the check fail,
     * while the message quotes the original ones.
     *
     * @param non-empty-string $assertion
     * @param non-empty-string $reason
     */
    private function comparisonFailure(string $expected, string $assertion, string $message, string $reason): ComparisonFailure
    {
        $failure = new ComparisonFailure(
            expected: $expected,
            actual: $this->subject,
            value: $this->parent->getValue(),
            assertion: $assertion,
            context: $message,
            reason: $reason . ', got ' . Support::stringify($this->value),
        );
        $this->parent->add($failure);

        return $failure;
    }

    /**
     * The check argument normalized like the subject.
     *
     * @param non-empty-string $check The check name for the error message.
     * @throws \InvalidArgumentException when a non-empty argument normalizes to an empty string:
     *         an empty argument matches any string, so the check would prove nothing.
     */
    private function argument(string $argument, string $check): string
    {
        $normalized = $this->normalizer->normalize($argument);
        $normalized === '' && $argument !== '' and throw new \InvalidArgumentException(\sprintf(
            'The argument "%s" of %s() is empty after normalization%s.',
            Support::escapeControlChars($argument),
            $check,
            $this->normalizer->describe(),
        ));

        return $normalized;
    }
}
