<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::array($a)->sameElementsAs([1, 2, 3]);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsArray($a);
        $this->assertEqualsCanonicalizing([1, 2, 3], $a);
    }
}
