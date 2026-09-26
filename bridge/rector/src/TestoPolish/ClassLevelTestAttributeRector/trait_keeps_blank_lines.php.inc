<?php

trait TraitBlankLinesTests
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
        $a = 1;

        \assert($a === 1);
    }
}

#[\Testo\Test]
final class TraitBlankLinesUserTest
{
    use TraitBlankLinesTests;
}
-----
<?php

trait TraitBlankLinesTests
{
    public function sharedScenario(): void
    {
        $a = 1;

        \assert($a === 1);
    }
}

#[\Testo\Test]
final class TraitBlankLinesUserTest
{
    use TraitBlankLinesTests;
}
