<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function testSomething(): void
    {
        if (!\extension_loaded('pcntl')) {
            $this->markTestSkipped('needs pcntl');
        }
    }
}
