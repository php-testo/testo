<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * A decorator that strips the common leading and trailing lines (emitting them as context) and only
 * runs the wrapped {@see Differ} on the differing middle.
 *
 * This is orthogonal to the choice of diff algorithm: for the typical assertion-failure shape — two
 * large blobs that share a long head and tail and differ in the middle — it collapses the inner
 * problem to just the changed region, so even an O(N·M) inner differ stays cheap. Wrap any `Differ`.
 *
 * @internal
 */
final class PrefixSuffixDiffer implements Differ
{
    public function __construct(
        private readonly Differ $inner = new MyersDiffer(),
    ) {}

    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);
        $n = \count($a);
        $m = \count($b);

        $start = 0;
        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $start++;
        }

        $endA = $n - 1;
        $endB = $m - 1;
        while ($endA >= $start && $endB >= $start) {
            \assert($endA >= 0 && $endB >= 0);
            if ($a[$endA] !== $b[$endB]) {
                break;
            }
            $endA--;
            $endB--;
        }

        $result = [];
        for ($i = 0; $i < $start; $i++) {
            $result[] = new DiffLine(DiffOp::Context, $a[$i]);
        }

        $midA = \array_slice($a, $start, $endA - $start + 1);
        $midB = \array_slice($b, $start, $endB - $start + 1);
        foreach ($this->middle($midA, $midB) as $line) {
            $result[] = $line;
        }

        for ($i = $endA + 1; $i < $n; $i++) {
            \assert($i >= 0);
            $result[] = new DiffLine(DiffOp::Context, $a[$i]);
        }

        return $result;
    }

    /**
     * Diff the trimmed middle. Degenerate one-sided middles are emitted directly; delegating them
     * through the string-based inner differ would misfire, since `explode("\n", "")` is `[""]`, not
     * an empty list. Only when both sides are non-empty is the work handed to the inner differ.
     *
     * @param list<string> $midA
     * @param list<string> $midB
     * @return list<DiffLine>
     */
    private function middle(array $midA, array $midB): array
    {
        if ($midA === [] && $midB === []) {
            return [];
        }
        if ($midA === []) {
            return \array_map(static fn(string $line): DiffLine => new DiffLine(DiffOp::Add, $line), $midB);
        }
        if ($midB === []) {
            return \array_map(static fn(string $line): DiffLine => new DiffLine(DiffOp::Remove, $line), $midA);
        }

        return $this->inner->diff(\implode("\n", $midA), \implode("\n", $midB));
    }
}
