<?php

class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(3)]
    public function test()
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Repeat(times: 3)]
    public function test()
    {
    }
}
