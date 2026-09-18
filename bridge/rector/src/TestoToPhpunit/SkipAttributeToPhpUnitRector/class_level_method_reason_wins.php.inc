<?php

#[\Testo\Skip('class reason')]
class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Skip('method reason')]
    public function testA(): void
    {
    }

    #[\Testo\Test]
    public function testB(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
        $this->markTestSkipped('method reason');
    }
    #[\Testo\Test]
    public function testB(): void
    {
        $this->markTestSkipped('class reason');
    }
}
