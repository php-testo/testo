<?php

final class FooTest extends \PHPUnit\Framework\TestCase implements \Countable
{
    #[\Override]
    protected function setUp(): void
    {
    }

    #[\Override]
    public function count(): int
    {
        return 0;
    }
}
-----
<?php

final class FooTest implements \Countable
{
    protected function setUp(): void
    {
    }

    #[\Override]
    public function count(): int
    {
        return 0;
    }
}
