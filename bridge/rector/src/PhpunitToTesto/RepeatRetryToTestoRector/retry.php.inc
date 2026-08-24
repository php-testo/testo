<?php

class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Retry(3)]
    public function test()
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Retry(maxAttempts: 3)]
    public function test()
    {
    }
}
