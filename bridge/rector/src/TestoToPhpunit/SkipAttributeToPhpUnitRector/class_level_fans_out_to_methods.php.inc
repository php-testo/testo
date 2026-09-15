<?php

#[\Testo\Skip('the whole case waits for the new API')]
class SomeTest
{
    #[\Testo\Test]
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
        $this->markTestSkipped('the whole case waits for the new API');
    }
    #[\Testo\Test]
    public function testB(): void
    {
        $this->markTestSkipped('the whole case waits for the new API');
    }
}
