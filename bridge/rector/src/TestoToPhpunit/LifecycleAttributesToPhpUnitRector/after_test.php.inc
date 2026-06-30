<?php

class FooTest
{
    #[\Testo\Lifecycle\AfterTest]
    public function cleanup(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\After]
    public function cleanup(): void
    {
    }
}
