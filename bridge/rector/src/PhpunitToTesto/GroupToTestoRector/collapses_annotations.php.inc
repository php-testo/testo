<?php

class FooTest
{
    /**
     * @group db
     * @group slow
     */
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
