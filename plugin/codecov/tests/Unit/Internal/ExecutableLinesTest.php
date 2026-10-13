<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\ExecutableLines;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
#[Covers(ExecutableLines::class)]
final class ExecutableLinesTest
{
    public function methodBodyLinesAreExecutable(): void
    {
        $source = <<<'PHP'
            <?php

            namespace App;

            use App\Other;

            final class Foo
            {
                private const LIMIT = 10;

                public function sum(int $a, int $b): int
                {
                    $sum = $a + $b;

                    return $sum;
                }
            }
            PHP;

        Assert::same(self::lines($source), [13, 15]);
    }

    public function closingBraceOfBodyWithoutReturnIsExecutable(): void
    {
        $source = <<<'PHP'
            <?php
            final class Foo
            {
                public function run(): void
                {
                    if ($this->ready) {
                        return;
                    }
                }

                public function fail(): never
                {
                    throw new \LogicException();
                }
            }
            PHP;

        Assert::same(self::lines($source), [6, 7, 9, 13]);
    }

    public function bodyLessMethodsAreSkipped(): void
    {
        $source = <<<'PHP'
            <?php
            interface Foo
            {
                public function run(): void;
            }

            abstract class Bar
            {
                abstract public function run(): void;
            }
            PHP;

        Assert::same(self::lines($source), []);
    }

    public function promotingConstructorSignatureIsExecutable(): void
    {
        $source = <<<'PHP'
            <?php
            final class Foo
            {
                public function __construct(
                    private string $name,
                ) {}
            }
            PHP;

        Assert::same(self::lines($source), [4, 6]);
    }

    public function interpolationDoesNotEndTheStatement(): void
    {
        $source = <<<'PHP'
            <?php
            function greet(string $name): string
            {
                return "Hello, {$name}!";
            }
            PHP;

        Assert::same(self::lines($source), [4]);
    }

    public function blockExpressionDoesNotEndTheStatement(): void
    {
        $source = <<<'PHP'
            <?php
            function label(int $code): string
            {
                return match ($code) {
                    1 => 'one',
                    default => 'many',
                };
            }
            PHP;

        Assert::same(self::lines($source), [5, 6]);
    }

    public function heredocContentIsNotExecutable(): void
    {
        $source = <<<'PHP'
            <?php
            function sample(): string
            {
                return <<<'SQL'
                    SELECT 1
                    SQL;
            }
            PHP;

        Assert::same(self::lines($source), [4]);
    }

    public function closureInsideMethodIsExecutable(): void
    {
        $source = <<<'PHP'
            <?php
            final class Foo
            {
                public function map(array $items): array
                {
                    return \array_map(static function (int $i): int {
                        return $i * 2;
                    }, $items);
                }
            }
            PHP;

        Assert::same(self::lines($source), [6, 7, 8]);
    }

    public function classConstantReferenceDoesNotOpenClassBody(): void
    {
        $source = <<<'PHP'
            <?php
            function name(): string
            {
                $class = Foo::class;
                return $class;
            }
            PHP;

        Assert::same(self::lines($source), [4, 5]);
    }

    public function topLevelStatementsAreExecutableButDeclarationsAreNot(): void
    {
        $source = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            use App\Kernel;

            $kernel = new Kernel();
            $kernel->boot();

            enum Status: string
            {
                case On = 'on';
            }
            PHP;

        Assert::same(self::lines($source), [9, 10]);
    }

    public function inlineHtmlStartingWithWhitespaceIsExecutable(): void
    {
        $source = "<?php\nfunction c1()\n{\n ?>\n   \n<div>x</div>\n <?php\n}\n";

        Assert::same(self::lines($source), [5, 8]);
    }

    /**
     * The expected lines are what Xdebug 3.4 on PHP 8.4 and PCOV on PHP 8.5 report for the fixture, where
     * they agree. Where they don't, the estimate takes one side: `classes` and `hooks` (Xdebug marks a
     * class whose declaration is delayed; PCOV marks promoted parameters instead of the constructor
     * signature), `jumptable` (Xdebug marks a `switch` dispatched through a table), `args` and
     * `class-constants` (PCOV leaves the first argument of a method call unmarked), `misc` and `toplevel`
     * (PCOV leaves `global` and a top-level `const` unmarked; Xdebug marks a conditionally declared
     * function), `heredoc-calls` (PCOV leaves the first text line of the second heredoc unmarked). In
     * `edge` both drivers leave the closing brace after a `switch` whose every case returns unmarked; the
     * estimate does not follow `switch` branches. In `namespaces` both drivers put the file's implicit
     * `return` on the brace closing the last namespace block, which holds no code. `constant-closures`
     * needs PHP 8.5 and is checked against PCOV only, as is `hooks`: Xdebug reports a property hook only
     * once it has run.
     *
     * A class or function declared in another file is taken as unknown while the file compiles, so
     * `class-constants` names one that does not exist: a loaded one would fold its constants.
     *
     * @param non-empty-string $fixture
     * @param list<int> $expected
     */
    #[DataSet(['altsyntax.php.inc', [4, 5, 6, 8, 9, 11, 13]], 'alternative syntax')]
    #[DataSet(['args.php.inc', [4, 5, 9, 12, 13, 14, 17, 20, 21, 22, 25, 29, 30, 36, 37, 39, 42]], 'call arguments')]
    #[DataSet(['classes.php.inc', [13, 17, 18, 22, 27, 32, 35, 45, 55]], 'classes, traits, enums, interfaces')]
    #[DataSet(['class-constants.php.inc', [9, 16, 17, 18, 20, 24, 28, 29, 30, 32, 37, 42, 43, 46, 47, 48, 50, 51, 52, 54, 55, 56, 58, 59, 60, 62, 63, 66]], 'class constants in arrays and static calls')]
    #[DataSet(['constant-closures.php.inc', [8, 29, 30, 34, 41]], 'closures in constant expressions')]
    #[DataSet(['edge.php.inc', [4, 5, 6, 8, 11, 16, 17, 19, 21, 24, 25, 26, 27, 30, 31, 34, 37, 38, 41, 42, 45]], 'argument and switch edges')]
    #[DataSet(['eval.php.inc', [4, 5]], 'eval')]
    #[DataSet(['generator.php.inc', [2, 4, 5, 6, 7]], 'generator')]
    #[DataSet(['generators.php.inc', [4, 6, 7, 9, 11, 13, 14, 16, 18, 24, 25, 26, 27, 30, 32, 33, 35, 36, 38, 40, 41, 42, 44, 47, 48, 50, 54]], 'generator methods, closures and arrow functions')]
    #[DataSet(['goto.php.inc', [4, 6, 7, 8, 10, 15, 16, 17, 22, 23, 24, 27, 29, 34, 36, 38, 40]], 'goto and labels')]
    #[DataSet(['halt.php.inc', [4, 6]], '__halt_compiler')]
    #[DataSet(['heredoc.php.inc', [4, 5, 6, 8, 11]], 'heredoc and nowdoc')]
    #[DataSet(['heredoc-calls.php.inc', [4, 5, 6, 7, 8, 9, 10, 11, 12, 15, 16, 18, 20, 21, 26, 27]], 'calls interpolated into heredoc')]
    #[DataSet(['hooks.php.inc', [5, 6, 11, 14, 15, 19, 20, 25, 26, 31, 37, 46, 48, 53, 55]], 'property hooks and asymmetric promotion')]
    #[DataSet(['include.php.inc', [4, 5, 6]], 'include and require')]
    #[DataSet(['jumptable.php.inc', [4, 5, 6, 12, 13, 18, 19, 20, 21, 22, 27, 28, 29, 30, 31, 32, 37, 38, 39, 45, 46, 52, 53, 54, 55, 57, 62, 63, 64, 65, 66, 67, 68, 69, 71, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 87, 91, 92, 93]], 'jump tables')]
    #[DataSet(['keywords.php.inc', [4, 5, 6, 8, 9, 10, 12, 13, 14, 16, 23, 28, 33, 38]], 'keywords as names')]
    #[DataSet(['loops.php.inc', [4, 5, 6, 8, 9, 11, 12, 15, 16, 17]], 'loops')]
    #[DataSet(['match.php.inc', [4, 5, 6, 7, 9, 10, 11, 13]], 'match and array()')]
    #[DataSet(['misc.php.inc', [4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 19, 20, 22]], 'static, global, list, closures, goto')]
    #[DataSet(['multiline.php.inc', [4, 5, 6, 8, 9, 10, 12, 13]], 'multi-line expressions')]
    #[DataSet(['namespaces.php.inc', [7, 11, 12, 17, 21, 29, 30, 41, 42, 43, 45, 50, 62, 71, 72, 74]], 'several namespaces, functions and classes')]
    #[DataSet(['reach.php.inc', [4, 5, 7, 12, 13, 15, 16, 19, 20, 21, 22, 24, 30, 32, 38, 39, 40, 46, 47, 48, 50, 53, 54, 56]], 'reachable closing braces')]
    #[DataSet(['returns.php.inc', [5, 11, 17, 18, 23, 24, 25, 29, 30, 35, 36, 38, 42, 43, 44, 46, 50, 51]], 'multi-line returns')]
    #[DataSet(['switch.php.inc', [5, 6, 7, 8, 9, 10, 11, 13, 15]], 'switch')]
    #[DataSet(['template.php.inc', [2, 3, 4, 6, 7]], 'inline HTML')]
    #[DataSet(['toplevel.php.inc', [7, 8, 10, 15, 18, 22]], 'top-level code')]
    #[DataSet(['trycatch.php.inc', [5, 6, 7, 8, 9, 10, 11, 13]], 'try, catch, finally')]
    public function matchesDriverLines(string $fixture, array $expected): void
    {
        $source = (string) \file_get_contents(\dirname(__DIR__, 2) . '/Fixture/ExecutableLines/' . $fixture);

        Assert::same(self::lines($source), $expected);
    }

    /**
     * `<?` opens PHP only under `short_open_tag`, a per-directory setting the test cannot switch, so the
     * expectation follows the running configuration.
     */
    public function openAndCloseTagsAcrossTheFile(): void
    {
        $source = (string) \file_get_contents(\dirname(__DIR__, 2) . '/Fixture/ExecutableLines/tags.php.inc');

        $expected = \filter_var(\ini_get('short_open_tag'), \FILTER_VALIDATE_BOOLEAN)
            ? [1, 2, 4, 6, 7, 8, 10, 11, 16, 17, 18, 20, 22, 23, 25]
            : [1, 2, 4, 6, 7, 8, 10, 16, 17, 18, 20, 22, 23, 25];

        Assert::same(self::lines($source), $expected);
    }

    /**
     * @return list<int>
     */
    private static function lines(string $source): array
    {
        return \array_keys(ExecutableLines::of($source));
    }
}
