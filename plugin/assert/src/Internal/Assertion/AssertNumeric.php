<?php

declare(strict_types=1);

namespace Testo\Assert\Internal\Assertion;

use Testo\Assert\Api\Builtin\IntType;
use Testo\Assert\Internal\Assertion\Traits\NumericTrait;
use Testo\Assert\State\Assertion\AssertionComposite;

/**
 * Assertion utilities for numeric data type.
 *
 * @internal
 * @psalm-internal Testo\Assert
 */
final readonly class AssertNumeric implements IntType
{
    use NumericTrait;

    public function __construct(
        public int|float $value,
        private readonly AssertionComposite $parent,
    ) {}
}
