<?php

class EmptyScalarSubjectTest
{
    // blank() treats "0" as valid data while PHPUnit calls it empty, so a scalar subject gets
    // PHP's own empty() check instead.
    public function test(string $value)
    {
        $this->assertEmpty($value);
        $this->assertNotEmpty($value, 'message');
    }
}
-----
<?php

class EmptyScalarSubjectTest
{
    // blank() treats "0" as valid data while PHPUnit calls it empty, so a scalar subject gets
    // PHP's own empty() check instead.
    public function test(string $value)
    {
        \Testo\Assert::true(empty($value));
        \Testo\Assert::false(empty($value), 'message');
    }
}
