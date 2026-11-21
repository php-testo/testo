<?php

/**
 * PHP 8.4 and 8.5 constructs: property hooks (adapted from the shipmonk-rnd/turbocov test fixtures,
 * MIT), asymmetric visibility, `new` without parentheses, the pipe operator, `clone` with properties,
 * a closure in a constant expression and a `switch` with many cases.
 */

declare(strict_types=1);

namespace Testo\CoverageProbe\Modern;

final class Temperature
{
    public float $celsius = 0.0 {
        get {
            return $this->celsius;
        }
        set(float $value) {
            if ($value < -273.15) {
                throw new \InvalidArgumentException('below absolute zero');
            }

            $this->celsius = $value;
        }
    }

    public float $fahrenheit {
        get => $this->celsius * 9 / 5
            + 32;
    }

    public string $unused {
        get {
            return 'never read';
        }
    }

    public string $label = '' {
        set => trim($value)
            ?: 'unnamed';
    }
}

final class Counter
{
    public private(set) int $count = 0;

    public protected(set) array $log = [];

    public function increment(int $by = 1): static
    {
        $this->count += $by > 0
            ? $by
            : throw new \InvalidArgumentException('positive step expected');
        $this->log[] = $by;

        return $this;
    }
}

final readonly class Point
{
    public function __construct(
        public int $x = 0,
        public int $y = 0,
    ) {}

    public function withX(int $x): self
    {
        return clone($this, [
            'x' => $x,
        ]);
    }
}

final class Text
{
    public static function slug(string $title): string
    {
        return $title
            |> trim(...)
            |> strtolower(...)
            |> (static fn(string $s): string => \str_replace(' ', '-', $s))
            |> (static fn(string $s): string => $s === ''
                ? 'empty'
                : $s);
    }

    public static function format(
        float $value,
        \Closure $formatter = static function (float $value): string {
            return \sprintf('%.1f', $value);
        },
    ): string {
        return $formatter($value);
    }
}

function zone(string $code): int
{
    switch ($code) {
        case 'eu':
            return 1;
        case 'us':
            return 2;
        case 'uk':
            return 3;
        case 'jp':
            return 4;
        case 'au':
            return 5;
        default:
            return 0;
    }
}

function script(): string
{
    $temperature = new Temperature();
    $temperature->celsius = 20.0;
    $temperature->label = '  ';
    $counter = new Counter()->increment()->increment(2);
    $point = new Point()->withX(3);

    return \implode(',', [
        $temperature->fahrenheit,
        $temperature->label,
        $counter->count,
        $point->x,
        Text::slug(' Hello World '),
        Text::format(1.5),
        zone('us'),
    ]);
}
