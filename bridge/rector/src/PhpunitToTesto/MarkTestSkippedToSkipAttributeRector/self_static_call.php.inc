<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function something(): void
    {
        self::markTestSkipped('needs the new API');
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    #[\Testo\Skip('needs the new API')]
    public function something(): void
    {
    }
}
