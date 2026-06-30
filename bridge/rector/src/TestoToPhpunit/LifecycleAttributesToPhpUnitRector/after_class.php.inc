<?php

class FooTest
{
    #[\Testo\Lifecycle\AfterClass]
    public function shutdown(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\AfterClass]
    public function shutdown(): void
    {
    }
}
