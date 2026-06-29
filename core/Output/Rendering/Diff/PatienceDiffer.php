<?php

declare(strict_types=1);

namespace Testo\Output\Rendering\Diff;

/**
 * Patience diff (Bram Cohen): align on lines that occur exactly once on each side, take the longest
 * increasing subsequence of those unique matches as stable anchors, then recurse into the gaps
 * between anchors. Regions with no unique common line fall back to the wrapped {@see Differ}.
 *
 * Unlike LCS/Myers it does not minimise the edit count — it optimises for *readable* alignment.
 * On inputs with repeated lines (blank lines, lone braces, boilerplate) the minimal-edit differs
 * tend to "slide" and pair up unrelated lines; anchoring on unique lines keeps related hunks
 * together, which matters when the diff is shown to a human in a failure report.
 *
 * @internal
 */
final class PatienceDiffer implements Differ
{
    public function __construct(
        private readonly Differ $fallback = new MyersDiffer(),
    ) {}

    #[\Override]
    public function diff(string $expected, string $actual): array
    {
        $a = \explode("\n", $expected);
        $b = \explode("\n", $actual);

        $out = [];
        $this->recurse($a, 0, \count($a), $b, 0, \count($b), $out);

        return $out;
    }

    /**
     * Pairs of (a-index, b-index) for lines occurring exactly once in each range, kept as the
     * longest subsequence that is increasing in both indices — the stable anchors.
     *
     * @param list<string> $a
     * @param list<string> $b
     * @return list<array{int, int}>
     */
    private static function uniqueAnchors(array $a, int $aLo, int $aHi, array $b, int $bLo, int $bHi): array
    {
        $countA = [];
        for ($i = $aLo; $i < $aHi; $i++) {
            $line = $a[$i];
            $countA[$line] = ($countA[$line] ?? 0) + 1;
        }

        $countB = [];
        $posB = [];
        for ($j = $bLo; $j < $bHi; $j++) {
            $line = $b[$j];
            $countB[$line] = ($countB[$line] ?? 0) + 1;
            $posB[$line] = $j;
        }

        // Walk A in order so the resulting pairs are already ascending in the a-index.
        $pairs = [];
        for ($i = $aLo; $i < $aHi; $i++) {
            $line = $a[$i];
            if ($countA[$line] === 1 && ($countB[$line] ?? 0) === 1) {
                $pairs[] = [$i, $posB[$line]];
            }
        }

        return self::longestIncreasingByB($pairs);
    }

    /**
     * Longest subsequence of $pairs that is strictly increasing in the b-index (the a-index is
     * already increasing). Patience-sorting in O(k log k) with back-pointers for reconstruction.
     *
     * @param list<array{int, int}> $pairs
     * @return list<array{int, int}>
     */
    private static function longestIncreasingByB(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        /** @var list<int> $piles piles[len-1] = index into $pairs of the smallest b-tail of a length-len run */
        $piles = [];
        /** @var array<int, int> $back back[idx] = predecessor index of $pairs[$idx] in its run */
        $back = [];
        foreach ($pairs as $idx => [, $bi]) {
            $lo = 0;
            $hi = \count($piles);
            while ($lo < $hi) {
                $mid = \intdiv($lo + $hi, 2);
                \assert($mid >= 0);
                if ($pairs[$piles[$mid]][1] < $bi) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid;
                }
            }
            $back[$idx] = $lo > 0 ? $piles[$lo - 1] : -1;
            $piles[$lo] = $idx;
        }

        $result = [];
        $k = $piles[\count($piles) - 1];
        while ($k !== -1) {
            \assert($k >= 0);
            $result[] = $pairs[$k];
            $k = $back[$k] ?? -1;
        }

        return \array_reverse($result);
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @param list<DiffLine> $out
     */
    private function recurse(array $a, int $aLo, int $aHi, array $b, int $bLo, int $bHi, array &$out): void
    {
        while ($aLo < $aHi && $bLo < $bHi && $a[$aLo] === $b[$bLo]) {
            $out[] = new DiffLine(DiffOp::Context, $a[$aLo]);
            $aLo++;
            $bLo++;
        }

        // The trailing common run is emitted only after the middle, so collect it reversed first.
        $suffix = [];
        while ($aHi > $aLo && $bHi > $bLo && $a[$aHi - 1] === $b[$bHi - 1]) {
            $aHi--;
            $bHi--;
            $suffix[] = new DiffLine(DiffOp::Context, $a[$aHi]);
        }

        $this->middle($a, $aLo, $aHi, $b, $bLo, $bHi, $out);

        for ($i = \count($suffix) - 1; $i >= 0; $i--) {
            $out[] = $suffix[$i];
        }
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @param list<DiffLine> $out
     */
    private function middle(array $a, int $aLo, int $aHi, array $b, int $bLo, int $bHi, array &$out): void
    {
        if ($aLo === $aHi && $bLo === $bHi) {
            return;
        }
        if ($aLo === $aHi) {
            for ($j = $bLo; $j < $bHi; $j++) {
                $out[] = new DiffLine(DiffOp::Add, $b[$j]);
            }
            return;
        }
        if ($bLo === $bHi) {
            for ($i = $aLo; $i < $aHi; $i++) {
                $out[] = new DiffLine(DiffOp::Remove, $a[$i]);
            }
            return;
        }

        $anchors = self::uniqueAnchors($a, $aLo, $aHi, $b, $bLo, $bHi);

        if ($anchors === []) {
            // No unique common line to anchor on; both ranges are non-empty here, so delegating
            // through the string-based fallback is safe (no empty-side explode pitfall).
            foreach ($this->fallback->diff(
                \implode("\n", \array_slice($a, $aLo, $aHi - $aLo)),
                \implode("\n", \array_slice($b, $bLo, $bHi - $bLo)),
            ) as $line) {
                $out[] = $line;
            }
            return;
        }

        $prevA = $aLo;
        $prevB = $bLo;
        foreach ($anchors as [$ai, $bi]) {
            $this->recurse($a, $prevA, $ai, $b, $prevB, $bi, $out);
            $out[] = new DiffLine(DiffOp::Context, $a[$ai]);
            $prevA = $ai + 1;
            $prevB = $bi + 1;
        }
        $this->recurse($a, $prevA, $aHi, $b, $prevB, $bHi, $out);
    }
}
