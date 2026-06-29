<?php

declare(strict_types=1);

namespace Testo\Spec\Internal;

/**
 * Turns collected {@see SpecEntry} fragments into an ordered, numbered document model, and provides
 * the matching ordering primitives used by the execution-reordering interceptors.
 *
 * The same ordering rules drive both, so the generated document mirrors the order tests actually ran:
 *
 * - **Sections** (Test Cases). A section with a manual number (class-level {@see \Testo\Spec\SpecHeader})
 *   comes first, sorted naturally by that number; sections without a number go to the end.
 * - **Items** (tests) within a section are numbered `{section}.{n}` in source-line order. A method
 *   that pins its own number keeps it; everything else is auto-numbered.
 * - **Collisions** — any two items that end up with the same number get a ` (1)`, ` (2)` … suffix in
 *   document order. This is the only disambiguation; numbers are never silently skipped.
 *
 * Shapes returned by {@see build()}:
 * - section: `array{number: non-empty-string, title: non-empty-string, items: list<numberedItem>}`
 * - numberedItem: `array{number: non-empty-string, title: non-empty-string, story: string, tags: list<non-empty-string>}`
 * - extra section: `array{title: non-empty-string|null, items: list<plainItem>}`
 * - plainItem: `array{title: non-empty-string|null, story: string, tags: list<non-empty-string>}`
 *
 * @internal
 * @psalm-internal Testo\Spec
 */
final readonly class SpecNumberer
{
    /**
     * @param list<SpecEntry> $entries
     * @return array{sections: list<array{number: non-empty-string, title: non-empty-string, items: list<array{number: non-empty-string, title: non-empty-string, story: string, tags: list<non-empty-string>}>}>, extra: list<array{title: non-empty-string|null, items: list<array{title: non-empty-string|null, story: string, tags: list<non-empty-string>}>}>}
     */
    public static function build(array $entries): array
    {
        // Group by case, preserving first-seen (run) order for the unnumbered tail.
        $cases = [];
        foreach ($entries as $entry) {
            $cases[$entry->case] ??= [
                'id' => $entry->case,
                'number' => $entry->sectionNumber,
                'title' => $entry->sectionTitle,
                'entries' => [],
            ];
            $cases[$entry->case]['entries'][] = $entry;
        }

        $numbered = [];
        $unnumbered = [];
        $index = 0;
        foreach ($cases as $case) {
            $case['index'] = $index++;
            $case['number'] === null ? $unnumbered[] = $case : $numbered[] = $case;
        }

        \usort($numbered, static function (array $a, array $b): int {
            $cmp = \strnatcmp((string) $a['number'], (string) $b['number']);
            return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
        });

        $sections = [];
        foreach ($numbered as $case) {
            $section = (string) $case['number'];
            $counter = 0;
            $items = [];
            foreach (self::order($case['entries'], $section) as $entry) {
                $items[] = [
                    'number' => $entry->number ?? $section . '.' . ++$counter,
                    'title' => $entry->heading(),
                    'story' => $entry->story,
                    'tags' => $entry->tags,
                ];
            }
            $sections[] = ['number' => $section, 'title' => $case['title'] ?? $case['id'], 'items' => $items];
        }

        self::disambiguate($sections);

        $extra = [];
        foreach ($unnumbered as $case) {
            $items = [];
            foreach (self::order($case['entries'], null) as $entry) {
                $items[] = ['title' => $entry->title, 'story' => $entry->story, 'tags' => $entry->tags];
            }
            $extra[] = ['title' => $case['title'], 'items' => $items];
        }

        return ['sections' => $sections, 'extra' => $extra];
    }

    /**
     * Compare two section (case) numbers for execution/document ordering: numbered before unnumbered,
     * numbered sorted naturally. Equal/both-null keep their original order (stable sort).
     */
    public static function compareSections(?string $a, ?string $b): int
    {
        if (($a === null) !== ($b === null)) {
            return $a === null ? 1 : -1;
        }

        return $a === null ? 0 : \strnatcmp($a, $b);
    }

    /**
     * Order test keys within a case the same way {@see build()} orders items, so execution order
     * matches the generated document.
     *
     * @param array<TKey, array{number: ?string, line: int}> $items
     * @param non-empty-string|null $section
     * @return list<TKey>
     *
     * @template TKey of array-key
     */
    public static function orderKeys(array $items, ?string $section): array
    {
        $byLine = $items;
        \uasort($byLine, static fn(array $a, array $b): int => $a['line'] <=> $b['line']);

        $rank = [];
        $position = 0;
        foreach ($byLine as $key => $_) {
            $rank[$key] = ++$position;
        }

        $effective = [];
        foreach ($items as $key => $item) {
            $effective[$key] = $item['number'] ?? ($section !== null ? $section . '.' . $rank[$key] : null);
        }

        $keys = \array_keys($items);
        \usort($keys, static function ($x, $y) use ($effective, $items): int {
            $a = $effective[$x];
            $b = $effective[$y];
            if (($a === null) !== ($b === null)) {
                return $a === null ? 1 : -1;
            }
            if ($a !== null && ($cmp = \strnatcmp($a, $b)) !== 0) {
                return $cmp;
            }

            return $items[$x]['line'] <=> $items[$y]['line'];
        });

        return $keys;
    }

    /**
     * @param list<SpecEntry> $entries
     * @param non-empty-string|null $section
     * @return list<SpecEntry>
     */
    private static function order(array $entries, ?string $section): array
    {
        $items = [];
        foreach ($entries as $i => $entry) {
            $items[$i] = ['number' => $entry->number, 'line' => $entry->line];
        }

        return \array_map(static fn(int $i): SpecEntry => $entries[$i], self::orderKeys($items, $section));
    }

    /**
     * Append ` (1)`, ` (2)` … to item numbers that repeat across the whole document, in document order.
     *
     * @param list<array{number: non-empty-string, title: non-empty-string, items: list<array{number: non-empty-string, title: non-empty-string, story: string, tags: list<non-empty-string>}>}> $sections
     * @param-out list<array{number: non-empty-string, title: non-empty-string, items: list<array{number: non-empty-string, title: non-empty-string, story: string, tags: list<non-empty-string>}>}> $sections
     */
    private static function disambiguate(array &$sections): void
    {
        $counts = [];
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                $counts[$item['number']] = ($counts[$item['number']] ?? 0) + 1;
            }
        }

        $seen = [];
        foreach ($sections as $s => $section) {
            foreach ($section['items'] as $i => $item) {
                if (($counts[$item['number']] ?? 0) < 2) {
                    continue;
                }
                $n = $seen[$item['number']] = ($seen[$item['number']] ?? 0) + 1;
                $sections[$s]['items'][$i]['number'] = $item['number'] . " ({$n})";
            }
        }
    }
}
