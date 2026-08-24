<?php

class SomeTest
{
    public function test(array $rows)
    {
        $this->assertEmpty($rows);
        $this->assertNotEmpty($rows, 'must have rows');
    }
}
-----
<?php

class SomeTest
{
    public function test(array $rows)
    {
        \Testo\Assert::blank($rows);
        \Testo\Assert::notBlank($rows, 'must have rows');
    }
}
