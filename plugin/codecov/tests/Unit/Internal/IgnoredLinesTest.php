<?php

declare(strict_types=1);

namespace Tests\Codecov\Unit\Internal;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Codecov\Internal\IgnoredLines;
use Testo\Test;

#[Test]
#[Covers(IgnoredLines::class)]
final class IgnoredLinesTest
{
    public function commentIgnoresItsOwnLine(): void
    {
        $source = <<<'PHP'
            <?php
            function run($x)
            {
                if ($x) {
                    return 1; // @codeCoverageIgnore
                }
                return 2;
            }
            PHP;

        Assert::same(self::lines($source), [5]);
    }

    public function commentWithAnythingElseIgnoresNothing(): void
    {
        $source = <<<'PHP'
            <?php
            $a = 1; // @codeCoverageIgnore because of reasons
            $b = 2; # @codeCoverageIgnore
            $c = 3; /* @codeCoverageIgnore */
            PHP;

        Assert::same(self::lines($source), []);
    }

    public function startAndEndIgnoreEveryLineBetween(): void
    {
        $source = <<<'PHP'
            <?php
            $a = 1;
            // @codeCoverageIgnoreStart
            $b = 2;
            $c = 3;
            // @codeCoverageIgnoreEnd
            $d = 4;
            PHP;

        Assert::same(self::lines($source), [3, 4, 5, 6]);
    }

    public function unclosedStartIgnoresNothing(): void
    {
        $source = <<<'PHP'
            <?php
            // @codeCoverageIgnoreStart
            $a = 1;
            PHP;

        Assert::same(self::lines($source), []);
    }

    public function endWithoutStartIgnoresItsOwnLine(): void
    {
        $source = <<<'PHP'
            <?php
            // @codeCoverageIgnoreStart
            $a = 1;
            // @codeCoverageIgnoreEnd
            $b = 2;
            // @codeCoverageIgnoreEnd
            PHP;

        Assert::same(self::lines($source), [2, 3, 4, 6]);
    }

    public function docblockIgnoresTheMethodWithItsAttributes(): void
    {
        $source = <<<'PHP'
            <?php
            final class Foo
            {
                /**
                 * @codeCoverageIgnore
                 */
                #[Attr(['a'])]
                public static function skipped(): string
                {
                    $s = "{$this->a} ${b}";
                    return \array_map(static function () {
                        return 1;
                    }, []);
                }

                public function counted(): int
                {
                    return 1;
                }
            }
            PHP;

        Assert::same(self::lines($source), [7, 8, 9, 10, 11, 12, 13, 14]);
    }

    public function docblockIgnoresWholeClassLikeOrFunction(): void
    {
        $source = <<<'PHP'
            <?php
            /** @codeCoverageIgnore */
            final class Whole
            {
                public function a(): int
                {
                    return 1;
                }
            }

            /** @codeCoverageIgnore */
            function free(): int
            {
                return 2;
            }

            /** @codeCoverageIgnore */
            enum Suit: string
            {
                case H = 'h';
            }
            PHP;

        Assert::same(self::lines($source), [3, 4, 5, 6, 7, 8, 9, 12, 13, 14, 15, 18, 19, 20, 21]);
    }

    public function docblockOfBodyLessMethodEndsAtItsSemicolon(): void
    {
        $source = <<<'PHP'
            <?php
            abstract class Foo
            {
                /** @codeCoverageIgnore */
                abstract protected function run(
                    int $a = 1,
                ): void;

                public function counted(): void {}
            }
            PHP;

        Assert::same(self::lines($source), [5, 6, 7]);
    }

    public function docblockOfAnythingElseIgnoresNothing(): void
    {
        $source = <<<'PHP'
            <?php
            final class Foo
            {
                /** @codeCoverageIgnore */
                public string $property = 'x';
            }

            /** @codeCoverageIgnore */
            $closure = function () {
                return 3;
            };

            /** @codeCoverageIgnore */
            $object = new class {
                public function run(): void {}
            };
            PHP;

        Assert::same(self::lines($source), []);
    }

    /**
     * Describing the annotation in a docblock is enough to ignore the whole class, so the plugin's own
     * sources must never spell it out there.
     */
    public function pluginSourcesIgnoreNothing(): void
    {
        $ignoring = [];
        $src = \dirname((string) (new \ReflectionClass(IgnoredLines::class))->getFileName(), 2);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($files as $file) {
            $file->getExtension() === 'php' && self::lines((string) \file_get_contents($file->getPathname())) !== []
                and $ignoring[] = $file->getFilename();
        }

        Assert::same($ignoring, []);
    }

    /**
     * @return list<int>
     */
    private static function lines(string $source): array
    {
        return \array_keys(IgnoredLines::of(\PhpToken::tokenize($source)));
    }
}
