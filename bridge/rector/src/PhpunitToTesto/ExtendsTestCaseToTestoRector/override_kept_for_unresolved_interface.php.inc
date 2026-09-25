<?php

final class FooTest extends \PHPUnit\Framework\TestCase implements \Missing\SomeInterface
{
    #[\Override]
    protected function setUp(): void
    {
    }
}
-----
<?php

final class FooTest implements \Missing\SomeInterface
{
    #[\Override]
    protected function setUp(): void
    {
    }
}
