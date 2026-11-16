<?php

class MarkedHelperTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertBase($value): void
    {
        $this->assertTrue($value);
    }

    protected function assertDelegates($value): void
    {
        $this->assertBase($value);
    }
}
-----
<?php

class MarkedHelperTest extends \PHPUnit\Framework\TestCase
{
    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertBase($value): void
    {
        $this->assertTrue($value);
    }

    #[\Testo\Common\Attribute\AssertMethod]
    protected function assertDelegates($value): void
    {
        $this->assertBase($value);
    }
}
