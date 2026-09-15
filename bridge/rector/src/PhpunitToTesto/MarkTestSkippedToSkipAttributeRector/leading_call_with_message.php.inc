<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function testSomething(): void
    {
        $this->markTestSkipped('not ready');
        $this->doSomething();
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Skip('not ready')]
    public function testSomething(): void
    {
        $this->doSomething();
    }
}
