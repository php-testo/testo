<?php

class SomeTest
{
    public function test()
    {
        $this->assertEqualsCanonicalizing([1, 2, 3], $actual);
        $this->assertEqualsCanonicalizing($expected, $actual, 'same set');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($actual)->sameElementsAs([1, 2, 3]);
        \Testo\Assert::array($actual)->sameElementsAs($expected, 'same set');
    }
}
