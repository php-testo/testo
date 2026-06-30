<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\Group('db')]
    #[\PHPUnit\Framework\Attributes\Group('slow')]
    public function testX(): void
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Filter\Group('db', 'slow')]
    public function testX(): void
    {
    }
}
