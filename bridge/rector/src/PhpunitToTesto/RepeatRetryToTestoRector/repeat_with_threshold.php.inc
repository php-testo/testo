<?php

class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(5, 2)]
    public function test()
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Repeat(times: 5, maxFailures: 1)]
    public function test()
    {
    }
}
