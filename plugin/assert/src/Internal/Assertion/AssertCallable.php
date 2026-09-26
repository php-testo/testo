<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\CallableType;
use Testo\Assert\Internal\StaticState;
use Testo\Assert\State\Assertion\AssertionComposite;
use Testo\Assert\State\Assertion\AssertionException;

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
     */
    public function __construct(
        private mixed $value,
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
        return new self($value, $parent);
    }
}
