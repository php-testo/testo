<?php

#[\Testo\Test]
#[\Testo\Filter\Group('slow')]
final class FooTest
{
    public function itWorks(): void
    {
    }
}
-----
<?php

#[\Testo\Filter\Group('slow')]
final class FooTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function itWorks(): void
    {
    }
}
