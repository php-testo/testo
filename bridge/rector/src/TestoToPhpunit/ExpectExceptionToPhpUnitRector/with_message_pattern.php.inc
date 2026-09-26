<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex('/boom/');
    }

    public function testDeprecated()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessagePattern('/bang/');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/boom/');
    }

    public function testDeprecated()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/bang/');
    }
}
