<?php

class FooTest
{
    #[\Testo\Lifecycle\BeforeTest]
    public function setUp(): void
    {
    }

    #[\Testo\Lifecycle\AfterTest]
    public function tearDown(): void
    {
    }
}
-----
<?php

class FooTest
{
    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }
}
