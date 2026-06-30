<?php

class FooTest
{
    protected function setUp(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
    }
}
