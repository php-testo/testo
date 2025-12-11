<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\ArrayType;
use Testo\Assert\Internal\Assertion\Traits\IterableTrait;
use Testo\Assert\State\AssertException;
use Testo\Assert\State\AssertTypeFailure;
use Testo\Assert\State\AssertTypeSuccess;
use Testo\Assert\StaticState;
use Testo\Assert\Support;

/**
 * Assertion utilities for arrays.
 *
 * @internal
 */
class AssertArray implements ArrayType
{
    use IterableTrait;

    public function __construct(
        private readonly array $value,
        private readonly AssertTypeSuccess $parent,
    ) {}

    /**
     * Validate that the given value is an array and return an AssertArray instance.
     *
     * @param mixed $value The value to be asserted as array.
     *
     * @throws AssertTypeFailure when the value is not an array.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_array($value) or StaticState::fail(AssertTypeFailure::create('array', $value));

        $parent = StaticState::typeSuccess('array', $value);
        return new self($value, $parent);
    }

    #[\Override]
    public function hasKey(int|string $key, string $message = ''): self
    {
        if (\array_key_exists($key, $this->value)) {
            $this->parent->log(
                \sprintf(
                    'Assert has key: %s.',
                    Support::stringify($key),
                ),
            );
            return $this;
        }
        $this->parent->fail(
            AssertException::fail(
                \sprintf(
                    'Failed to assert that array %s has key %s.',
                    Support::stringify($this->value),
                    Support::stringify($key),
                ),
            ),
        );
    }

    #[\Override]
    public function isList(string $message = ''): ArrayType
    {
        if (\array_is_list($this->value)) {
            $this->parent->log('Assert array is list.');

            return $this;
        }

        $this->parent->fail(
            AssertException::fail(
                \sprintf(
                    'Failed to assert that array %s is a list.',
                    Support::stringify($this->value),
                ),
            ),
        );
    }
}
