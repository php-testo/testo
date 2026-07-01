<?php

class SomeTest
{
    public function test()
    {
        $this->assertContains($needle, $haystack);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::contains($haystack, $needle);
    }
}
