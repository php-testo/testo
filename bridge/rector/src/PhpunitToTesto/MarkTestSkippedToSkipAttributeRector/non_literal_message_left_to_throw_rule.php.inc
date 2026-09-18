<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function testSomething(): void
    {
        $this->markTestSkipped($this->reason());
    }
}
