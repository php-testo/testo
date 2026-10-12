<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Driver;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\Driver\UncountedLines;
use Testo\Data\DataSet;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

/**
 * Xdebug and PCOV report the implicit `return` of a file's top-level code on the file's last line.
 * The line stays unexecuted whenever the file is loaded outside a coverage window.
 */
#[Test]
#[Covers(UncountedLines::class)]
final class UncountedLinesTest
{
    private const SOURCE = "<?php\n\nfinal class Foo\n{\n    public function bar(): int\n    {\n        return 1;\n    }\n}\n";

    private string $root;

    #[BeforeTest]
    public function createRoot(): void
    {
        $this->root = \dirname(__DIR__, 2) . '/runtime/uncounted_lines_' . \uniqid();
        \mkdir($this->root, recursive: true);
    }

    #[AfterTest]
    public function removeRoot(): void
    {
        foreach (\glob($this->root . '/*') as $file) {
            \unlink($file);
        }
        \rmdir($this->root);
    }

    public function removeDropsLinePastFinalNewline(): void
    {
        $file = $this->write(self::SOURCE);

        $result = UncountedLines::remove([$file => [7 => 1, 10 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeDropsLastLineAfterManyBlankLines(): void
    {
        $file = $this->write(self::SOURCE . "\n\n   \n\t\n\n");

        $result = UncountedLines::remove([$file => [7 => 1, 15 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeDropsClosingBraceOfClassEndingFile(): void
    {
        $file = $this->write(\rtrim(self::SOURCE));

        $result = UncountedLines::remove([$file => [7 => 1, 9 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeDropsClosingBraceOfNamespaceEndingFile(): void
    {
        $file = $this->write("<?php\nnamespace App {\n    run();\n}");

        $result = UncountedLines::remove([$file => [3 => 1, 4 => 1]]);

        Assert::same($result, [$file => [3 => 1]]);
    }

    public function removeKeepsClosingBraceOfFunctionEndingFile(): void
    {
        $file = $this->write("<?php\nfunction run(): void\n{\n    go();\n}");

        $result = UncountedLines::remove([$file => [4 => -1, 5 => 1]]);

        Assert::same($result, [$file => [4 => -1, 5 => 1]]);
    }

    public function removeDropsClosingTagEndingFile(): void
    {
        $file = $this->write(self::SOURCE . "?>\n\n");

        $result = UncountedLines::remove([$file => [7 => 1, 10 => -1, 11 => -1, 12 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeDropsCommentEndingFile(): void
    {
        $file = $this->write(self::SOURCE . '// end');

        $result = UncountedLines::remove([$file => [7 => 1, 10 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeDropsBlankLinesOfCrlfFile(): void
    {
        $file = $this->write(\str_replace("\n", "\r\n", self::SOURCE));

        $result = UncountedLines::remove([$file => [2 => -1, 7 => 1, 10 => -1]]);

        Assert::same($result, [$file => [7 => 1]]);
    }

    public function removeKeepsStatementOnLastLine(): void
    {
        $file = $this->write("<?php\nrun();");

        $result = UncountedLines::remove([$file => [2 => 1]]);

        Assert::same($result, [$file => [2 => 1]]);
    }

    /**
     * A heredoc that interpolates is assembled from the line of its first text part, blank or not.
     */
    public function removeKeepsBlankFirstLineOfHeredoc(): void
    {
        $file = $this->write("<?php\n\$x = <<<EOT\n\n    {\$a} tail\n    EOT;\n");

        $result = UncountedLines::remove([$file => [2 => 1, 3 => 1, 6 => 1]]);

        Assert::same($result, [$file => [2 => 1, 3 => 1]]);
    }

    public function removeDropsLinePastFinalNewlineFromBranchEntry(): void
    {
        $file = $this->write(self::SOURCE);

        $result = UncountedLines::remove([$file => ['lines' => [7 => 1, 10 => 1], 'functions' => []]]);

        Assert::same($result, [$file => ['lines' => [7 => 1], 'functions' => []]]);
    }

    /**
     * Raw data is what Xdebug 3.4 (PHP 8.4) or PCOV (PHP 8.5) reports for the source. Xdebug marks dead code
     * `-2` and reports a file's top-level code only when the file loads inside the coverage window; PCOV
     * reports every line of a loaded file, `-1` when it loaded before the window.
     *
     * @param array<int, int> $raw
     * @param array<int, int> $expected
     */
    #[DataSet(["<?php\n\nenum Suit: string\n{\n    case A = 'a';\n\n    public function label(): string\n    {\n        return \$this->value;\n    }\n}", [3 => 1, 9 => 1, 10 => -2, 11 => 1], [3 => 1, 9 => 1, 10 => -2]], 'enum, Xdebug')]
    #[DataSet(["<?php\n\ntrait Greets\n{\n    public function hi(): string\n    {\n        return 'hi';\n    }\n}", [7 => 1, 9 => -1], [7 => 1]], 'trait, PCOV')]
    #[DataSet(["<?php\n\ninterface Shape\n{\n    const SIDES = 0;\n\n    public function area(): float;\n}", [8 => 1], []], 'interface, Xdebug')]
    #[DataSet(["<?php\n\nnamespace App;\n\nfinal class Foo\n{\n    public function bar(): int\n    {\n        return 1;\n    }\n}", [9 => 1, 10 => -2, 11 => 1], [9 => 1, 10 => -2]], 'class in a file-scoped namespace, Xdebug')]
    #[DataSet(["<?php\n\nfinal class Bag\n{\n    public function put(): void\n    {\n        \$x = 1;\n    }\n}", [7 => 1, 8 => 1, 9 => 1], [7 => 1, 8 => 1]], 'class whose last method falls through, Xdebug')]
    #[DataSet(["<?php\n\nfinal class Two\n{\n    public function m(): void\n    {\n        \$x = 1;\n    }}", [7 => 1, 8 => 1], [7 => 1, 8 => 1]], 'method and class closed on one line, Xdebug')]
    #[DataSet(["<?php\n\nfunction one(): int\n{\n    return 1;\n}", [5 => 1, 6 => -1], [5 => 1]], 'function ending in return, PCOV')]
    #[DataSet(["<?php\n\nfunction fail(): never\n{\n    throw new \\LogicException('x');\n}", [5 => 1, 6 => 1], [5 => 1]], 'function ending in throw, Xdebug')]
    #[DataSet(["<?php\n\nfunction pick(int \$x): int\n{\n    if (\$x) {\n        return 1;\n    } else {\n        return 0;\n    }\n}", [5 => 1, 6 => 1, 8 => -1, 10 => 1], [5 => 1, 6 => 1, 8 => -1]], 'function whose every branch returns, Xdebug')]
    #[DataSet(["<?php\n\nfunction pick(int \$x): int\n{\n    if (\$x) {\n        return 1;\n    }\n    \$y = 0;\n}", [5 => 1, 6 => 1, 8 => -1, 9 => -1], [5 => 1, 6 => 1, 8 => -1, 9 => -1]], 'function falling through past an if, PCOV')]
    #[DataSet(["<?php\n\n\$g = function (): void {\n    \$x = 1;\n};", [3 => -1, 4 => 1, 5 => 1], [3 => -1, 4 => 1, 5 => 1]], 'closure falling through, PCOV')]
    #[DataSet(["<?php\n\n\$x = 1;\nif (\$x) {\n    \$y = 2;\n}", [3 => 1, 4 => 1, 5 => 1, 6 => 1], [3 => 1, 4 => 1, 5 => 1]], 'top-level if, Xdebug')]
    #[DataSet(["<?php\n\nfunction one(): int\n{\n    return 1;\n}\n\none();\n?>", [5 => -1, 8 => -1, 9 => -1], [5 => -1, 8 => -1]], 'closing tag, PCOV')]
    #[DataSet(["<?php\n\nfunction one(): int\n{\n    return 1;\n}\n\none();\n?>\n", [5 => 1, 6 => -2, 8 => 1, 10 => 1], [5 => 1, 6 => -2, 8 => 1]], 'closing tag and newline, Xdebug')]
    #[DataSet(["<?php\n\nfinal class Foo\n{\n    public function bar(): int\n    {\n        return 1;\n    }\n}\n?>", [7 => 1, 8 => -2, 10 => 1], [7 => 1, 8 => -2]], 'closing tag after a class, Xdebug')]
    #[DataSet(["<?php\n\$a = 1;\n?>   ", [2 => -1, 3 => -1], [2 => -1]], 'closing tag and spaces, PCOV')]
    #[DataSet(["<?php\n\nfunction one(): int\n{\n    return 1;\n}\n\none();\n?>\n<p>footer</p>\n", [5 => -1, 8 => -1, 10 => -1, 11 => -1], [5 => -1, 8 => -1, 10 => -1]], 'closing tag and HTML, PCOV')]
    #[DataSet(["<?php\n\$a = 1;\n?>\n\n<p>footer</p>\n", [2 => 1, 4 => 1, 6 => 1], [2 => 1, 4 => 1]], 'closing tag, blank line and HTML, Xdebug')]
    #[DataSet([self::SOURCE . '# end', [7 => 1, 10 => -1], [7 => 1]], 'hash comment, PCOV')]
    #[DataSet([self::SOURCE . '/* end */', [7 => 1, 8 => -2, 10 => 1], [7 => 1, 8 => -2]], 'block comment, Xdebug')]
    #[DataSet(["<?php\n\nfinal class Foo\n{\n    public function bar(): int\n    {\n        return 1;\n    }\n} // end", [7 => 1, 9 => -1], [7 => 1]], 'comment after the closing brace, PCOV')]
    #[DataSet(["<?php\r\n\r\nfinal class Foo\r\n{\r\n    public function bar(): int\r\n    {\r\n        return 1;\r\n    }\r\n}", [7 => 1, 8 => -2, 9 => 1], [7 => 1, 8 => -2]], 'CRLF without final newline, Xdebug')]
    #[DataSet(["<?php\n\n\$x = 1;\n__halt_compiler();\nraw data\n}\n\nmore", [3 => -1, 4 => -1], [3 => -1, 4 => -1]], '__halt_compiler() and data, PCOV')]
    #[DataSet(["<?php\n\n\$o = new class {\n    public function m(): int\n    {\n        return 1;\n    }\n};", [3 => -1, 6 => 1, 8 => -1], [3 => -1, 6 => 1]], 'anonymous class assigned, PCOV')]
    #[DataSet(["<?php\n\n\$x = max(\n    1,\n    2,\n);", [3 => -1, 6 => -1], [3 => -1]], 'multi-line call, PCOV')]
    #[DataSet(['<?php', [1 => 1], []], 'open tag alone, Xdebug')]
    #[DataSet(["<?php\n", [2 => -1], []], 'open tag and newline, PCOV')]
    public function removeDropsImplicitReturnAtFileEnd(string $source, array $raw, array $expected): void
    {
        $file = $this->write($source);

        $result = UncountedLines::remove([$file => $raw]);

        Assert::same($result, [$file => $expected]);
    }

    /**
     * @param array<int, int> $raw
     * @param array<int, int> $expected
     */
    #[DataSet(["<?php\n\$x = <<<'EOT'\n\n    plain\n\n    EOT;\n\$y = 2;\n", [2 => 1, 7 => 1, 8 => 1], [2 => 1, 7 => 1]], 'nowdoc, Xdebug')]
    #[DataSet(["<?php\n\$x = <<<EOT\n\n    plain\n\n    EOT;\n", [2 => 1, 7 => 1], [2 => 1]], 'heredoc without interpolation, PCOV')]
    #[DataSet(["<?php\n\$x = 'one\n\ntwo';\n\$y = \"a\n\n{\$x}\";\n", [2 => 1, 5 => 1, 7 => 1, 8 => 1], [2 => 1, 5 => 1, 7 => 1]], 'multi-line strings, PCOV')]
    #[DataSet(["<?php\n\n/**\n * Doc.\n *\n * More.\n */\nfunction d(): int\n{\n    return 1;\n}\n\nd();\n", [10 => 1, 11 => -2, 13 => 1, 14 => 1], [10 => 1, 11 => -2, 13 => 1]], 'docblock, Xdebug')]
    #[DataSet(
        ["<?php\n\nfunction add(int ...\$n): int\n{\n    return array_sum(\$n);\n}\n\n\$x = add(\n    1,\n\n    2,\n);\n\$y = \$x\n    + 1\n    + 2;\n\$z = [\n    'a' => 1,\n\n    'b' => \$y,\n];\n",
            [5 => 1, 6 => -2, 8 => 1, 9 => 1, 11 => 1, 13 => 1, 14 => 1, 15 => 1, 16 => 1, 17 => 1, 19 => 1, 21 => 1],
            [5 => 1, 6 => -2, 8 => 1, 9 => 1, 11 => 1, 13 => 1, 14 => 1, 15 => 1, 16 => 1, 17 => 1, 19 => 1]],
        'multi-line expressions, Xdebug',
    )]
    #[DataSet(
        ["<?php \$items = ['a', 'b']; \$t = 'T'; ?>\n<html>\n<title><?= \$t ?></title>\n<?php foreach (\$items as \$i): ?>\n    <li><?= \$i ?></li>\n<?php endforeach; ?>\n<?php if (\$t): ?>\n    <b>yes</b>\n<?php else: ?>\n    <i>no</i>\n<?php endif ?>\n</html>\n",
            [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 7 => 1, 8 => 1, 10 => -1, 12 => 1, 13 => 1],
            [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 7 => 1, 8 => 1, 10 => -1, 12 => 1]],
        'template with alternative syntax, Xdebug',
    )]
    #[DataSet(["<?php \$a = 1; ?>\n\n<?php \$b = 2;\n", [1 => 1, 2 => 1, 3 => 1, 4 => 1], [1 => 1, 3 => 1]], 'blank line echoed between tags, PCOV')]
    public function removeKeepsLinesWhereCodeStarts(string $source, array $raw, array $expected): void
    {
        $file = $this->write($source);

        $result = UncountedLines::remove([$file => $raw]);

        Assert::same($result, [$file => $expected]);
    }

    public function removeKeepsHtmlAfterWhitespaceLine(): void
    {
        $file = $this->write("<?php\nfunction c1()\n{\n ?>\n   \n<div>x</div>\n <?php\n}\n");

        $result = UncountedLines::remove([$file => [5 => 1, 8 => 1, 9 => 1]]);

        Assert::same($result, [$file => [5 => 1, 8 => 1]]);
    }

    /**
     * A file is scanned once per process: a later change to it goes unseen.
     */
    public function removeReusesFirstScanOfPath(): void
    {
        $file = $this->write(self::SOURCE);
        $raw = [$file => [7 => 1, 8 => -2, 10 => 1]];
        $first = UncountedLines::remove($raw);
        \file_put_contents($file, self::SOURCE . "run();\n");

        $second = UncountedLines::remove($raw);

        Assert::same($first, [$file => [7 => 1, 8 => -2]]);
        Assert::same($second, $first);
    }

    /**
     * @param list<int> $out
     * @param list<int> $outHit
     */
    private static function branch(int $opStart, int $opEnd, int $lineStart, int $lineEnd, int $hit, array $out, array $outHit): array
    {
        return [
            'op_start' => $opStart, 'op_end' => $opEnd, 'line_start' => $lineStart, 'line_end' => $lineEnd,
            'hit' => $hit, 'out' => $out, 'out_hit' => $outHit,
        ];
    }

    private function write(string $source): string
    {
        $file = $this->root . '/' . \uniqid('src_') . '.php';
        \file_put_contents($file, $source);

        return $file;
    }
}
