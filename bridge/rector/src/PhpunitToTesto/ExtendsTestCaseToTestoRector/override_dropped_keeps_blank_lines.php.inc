<?php

final class OverrideBlankLinesTest extends \PHPUnit\Framework\TestCase
{
    private int $value = 0;

    #[\Override]
    protected function setUp(): void
    {
        $this->value = 1;

        $this->value++;
    }

    public function testFoo(): void
    {
        \assert($this->value === 2);
    }
}
-----
<?php

final class OverrideBlankLinesTest
{
    private int $value = 0;

    protected function setUp(): void
    {
        $this->value = 1;

        $this->value++;
    }

    #[\Testo\Test]
    public function testFoo(): void
    {
        \assert($this->value === 2);
    }
}
