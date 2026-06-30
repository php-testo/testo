<?php

class FooTest
{
    protected function tearDown(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Lifecycle\AfterTest]
    protected function tearDown(): void
    {
    }
}
