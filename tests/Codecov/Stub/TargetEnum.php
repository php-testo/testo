<?php

declare(strict_types=1);

namespace Tests\Codecov\Stub;

/**
 * Dummy enum used as a coverage target.
 */
enum TargetEnum: string
{
    case Alpha = 'alpha';
    case Beta = 'beta';

    public function label(): string
    {
        return match ($this) {
            self::Alpha => 'Alpha Label',
            self::Beta => 'Beta Label',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'Alpha Label' => self::Alpha,
            'Beta Label' => self::Beta,
            default => throw new \ValueError("Unknown label: {$label}"),
        };
    }
}
