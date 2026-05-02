<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\IterableType;
use Testo\Assert\Internal\Assertion\Traits\IterableTrait;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;

/**
 * Assertion utilities for iterables.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertIterable implements IterableType
{
    use IterableTrait;

    public function __construct(
        private iterable $value,
        private AssertionComposite $parent,
    ) {}

    /**
     * Validate that the given value is an iterable and return an AssertIterable instance.
     *
     * @param mixed $value The value to be asserted as an iterable.
     *
     * @throws AssertionException when the value is not an iterable.
     */
    public static function validateAndCreate(mixed $value): self
    {
        \is_iterable($value) or StaticState::typeFail('iterable', $value);

        $parent = StaticState::typeSuccess('iterable', $value);
        return new self($value, $parent);
    }
}
