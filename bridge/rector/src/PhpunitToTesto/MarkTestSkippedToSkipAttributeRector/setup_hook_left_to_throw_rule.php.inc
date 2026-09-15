<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped('the whole case is on hold');
    }
}
