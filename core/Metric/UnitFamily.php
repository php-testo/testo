<?php

declare(strict_types=1);

namespace Testo\Metric;

/**
 * Everything the metric core knows about one {@see Unit} enum, read once from its attributes.
 *
 * Obtained through {@see Units::family()}, which keeps one instance per enum so the reflection happens
 * once per process.
 *
 * @api
 */
final readonly class UnitFamily
{
    /**
     * @param class-string<Unit> $class
     * @param non-empty-string $name The {@see Dimension} name.
     * @param non-empty-list<Unit> $units Every case, smallest factor first, declaration order among equals.
     * @param non-empty-array<non-empty-string, int|float> $factors {@see Factor} of each case, keyed by case name.
     */
    public function __construct(
        public string $class,
        public string $name,
        public array $units,
        public array $factors,
    ) {}

    public function factor(Unit $unit): int|float
    {
        return $this->factors[$unit->name];
    }

    /**
     * The unit every factor is expressed in.
     */
    public function base(): Unit
    {
        return $this->units[0];
    }

    /**
     * The unit in which a base-unit amount reads most compactly: the largest one it still reaches at
     * least one of, so `1500` base units of a thousand-step family become `1.5` of the next unit up.
     * Amounts below one of the smallest unit, and zero, stay in the smallest.
     */
    public function fit(int|float $baseValue): Unit
    {
        $magnitude = \abs($baseValue);
        $fit = $this->base();
        foreach ($this->units as $unit) {
            $magnitude >= $this->factor($unit) and $fit = $unit;
        }

        return $fit;
    }
}
