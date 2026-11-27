<?php

declare(strict_types=1);

namespace Testo\Output\Rendering;

use Testo\Core\Context\TestResult;
use Testo\Core\Metadata\MetadataEntry;
use Testo\Metric\Metric;

/**
 * A test's metadata as data, for the documents that carry it structurally (HTML, JSON).
 *
 * A number keeps its native value and names its unit separately; every other kind carries its string.
 *
 * A benchmark's own `bench.*` entries are left out of a result that carries the benchmark itself: the
 * document already holds the same measurements as the structured `bench` block {@see BenchMapper} builds,
 * and a second, flat copy of the grid would only double the payload. Entries the test added on its own
 * stay.
 *
 * @internal
 */
final class MetadataMapper
{
    private function __construct() {}

    /**
     * @return list<array{name: non-empty-string, type: non-empty-string, value: int|float|string|null, unit?: string}>
     */
    public static function map(TestResult $result): array
    {
        $bench = BenchMapper::supports($result->result);

        $entries = [];
        foreach ($result->metadata as $entry) {
            $bench && \str_starts_with($entry->name, 'bench.') or $entries[] = self::entry($entry);
        }

        return $entries;
    }

    /**
     * @return array{name: non-empty-string, type: non-empty-string, value: int|float|string|null, unit?: string}
     */
    private static function entry(MetadataEntry $entry): array
    {
        $value = $entry->value;
        if (!$value instanceof Metric) {
            return ['name' => $entry->name, 'type' => $entry->kind->value, 'value' => $value];
        }

        $number = $value->value;
        # JSON has no literal for NaN or infinity; null states "no number" without aborting the document.
        \is_float($number) && !\is_finite($number) and $number = null;

        return [
            'name' => $entry->name,
            'type' => $entry->kind->value,
            'value' => $number,
            'unit' => (string) $value->unit->value,
        ];
    }
}
