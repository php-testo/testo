<?php

/**
 * Keeps its docblock.
 */
#[\Testo\Test]
final class BlankLinesTest
{
    public function itWorks(): void
    {
        $a = 1;

        \assert($a === 1);
    }

    public function alsoWorks(): void
    {
    }
}
-----
<?php

/**
 * Keeps its docblock.
 */
final class BlankLinesTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
        $a = 1;

        \assert($a === 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function alsoWorks(): void
    {
    }
}
