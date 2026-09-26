<?php

#[\Testo\Test]
final class BlankLinesTest
{
    #[\Testo\Skip('broken')]
    public function testA(): void
    {
        $a = 1;

        \assert($a === 1);
    }
}
-----
<?php

#[\Testo\Test]
final class BlankLinesTest
{
    public function testA(): void
    {
        $this->markTestSkipped('broken');
        $a = 1;

        \assert($a === 1);
    }
}
