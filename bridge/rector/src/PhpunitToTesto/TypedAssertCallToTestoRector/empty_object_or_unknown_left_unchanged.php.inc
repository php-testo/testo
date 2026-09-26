<?php

class EmptyObjectSubjectTest
{
    // PHPUnit counts a Countable subject, which empty() does not; an untyped subject may be one.
    public function test(\Countable $items, $value)
    {
        $this->assertEmpty($items);
        $this->assertNotEmpty($value);
    }
}
