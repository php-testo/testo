<?php

class SomeTest
{
    // A string subject could be "0", which blank() treats as valid (non-blank) data — converting it
    // would change meaning, so a non-array assertEmpty/assertNotEmpty is left untouched.
    public function test(string $value)
    {
        $this->assertEmpty($value);
        $this->assertNotEmpty($value);
    }
}
