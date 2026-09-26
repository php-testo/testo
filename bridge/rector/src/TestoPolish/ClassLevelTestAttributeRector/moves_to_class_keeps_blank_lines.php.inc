<?php

final class ClassLevelBlankLinesTest
{
    #[\Testo\Test]
    public function first(): void
    {
        $a = 1;

        \assert($a === 1);
    }

    /**
     * Keeps its docblock.
     */
    #[\Testo\Test]
    public function second(): void
    {
        $b = 2;

        \assert($b === 2);
    }

    #[\Testo\Test] public function third(
        int $c = 3,
    ): void {
        \assert($c === 3);
    }
}
-----
<?php

#[\Testo\Test]
final class ClassLevelBlankLinesTest
{
    public function first(): void
    {
        $a = 1;

        \assert($a === 1);
    }

    /**
     * Keeps its docblock.
     */
    public function second(): void
    {
        $b = 2;

        \assert($b === 2);
    }

    public function third(
        int $c = 3,
    ): void {
        \assert($c === 3);
    }
}
