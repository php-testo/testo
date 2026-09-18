<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function testSomething(): void
    {
        $this->markTestSkipped();
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Skip]
    public function testSomething(): void
    {
    }
}
