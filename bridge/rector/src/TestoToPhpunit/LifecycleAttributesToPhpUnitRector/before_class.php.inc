<?php

class FooTest
{
    #[\Testo\Lifecycle\BeforeClass]
    public function bootstrap(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\BeforeClass]
    public function bootstrap(): void
    {
    }
}
