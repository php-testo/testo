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
        $max = \min($n, $m);

        // Lengths of the shared head and tail. Both counters start at 0, so every index derived from
        // them stays non-negative by construction — no per-iteration assertion needed.
        $prefix = 0;
        while ($prefix < $max && $a[$prefix] === $b[$prefix]) {
            ++$prefix;
        }

        $suffix = 0;
        while ($suffix < $max - $prefix) {
            $ia = $n - 1 - $suffix;
            $ib = $m - 1 - $suffix;
            // The loop bound keeps $ia/$ib >= $prefix, so they never go negative; the explicit guard
            // both states that invariant and lets static analysis prove the accesses are in range.
            if ($ia < 0 || $ib < 0 || $a[$ia] !== $b[$ib]) {
                break;
            }
            ++$suffix;
        }

        $result = [];
        foreach (\array_slice($a, 0, $prefix) as $line) {
            $result[] = new DiffLine(DiffOp::Context, $line);
        }
        foreach ($this->middle(
            \array_slice($a, $prefix, $n - $prefix - $suffix),
            \array_slice($b, $prefix, $m - $prefix - $suffix),
        ) as $line) {
            $result[] = $line;
        }
        foreach (\array_slice($a, $n - $suffix) as $line) {
            $result[] = new DiffLine(DiffOp::Context, $line);
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
