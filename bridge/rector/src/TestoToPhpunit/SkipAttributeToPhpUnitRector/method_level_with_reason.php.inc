<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Skip('broken by the pricing rework')]
    public function testA(): void
    {
        $this->doSomething();
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
        $this->markTestSkipped('broken by the pricing rework');
        $this->doSomething();
    }
}
