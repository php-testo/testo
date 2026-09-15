<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Skip]
    public function testA(): void
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
        $this->markTestSkipped();
    }
}
