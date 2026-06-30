<?php

class FooTest
{
    #[\Testo\Lifecycle\BeforeTest]
    public function prepare(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\Before]
    public function prepare(): void
    {
    }
}
