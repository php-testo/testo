<?php

#[\Testo\Test]
final class ClassLevelRedundantBlankLinesTest
{
    #[\Testo\Test]
    public function first(): void
    {
        $a = 1;

        \assert($a === 1);
    }
}
-----
<?php

#[\Testo\Test]
final class ClassLevelRedundantBlankLinesTest
{
    public function first(): void
    {
        $a = 1;

        \assert($a === 1);
    }
}
