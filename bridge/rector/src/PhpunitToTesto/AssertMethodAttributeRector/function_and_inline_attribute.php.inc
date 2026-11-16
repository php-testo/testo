<?php

function assertPositive(int $value): void
{
    \PHPUnit\Framework\assertGreaterThan(0, $value);
}

function assertNonEmpty(string $value): void
{
    \Testo\Assert::string($value)->notEmpty();
}

class InlineAttributeTest extends \PHPUnit\Framework\TestCase
{
    #[\Deprecated] protected function assertInline($value): void
    {
        $this->assertTrue($value);
    }
}
-----
<?php

#[\Testo\Common\Attribute\AssertMethod]
function assertPositive(int $value): void
{
    \PHPUnit\Framework\assertGreaterThan(0, $value);
}

#[\Testo\Common\Attribute\AssertMethod]
function assertNonEmpty(string $value): void
{
    \Testo\Assert::string($value)->notEmpty();
}

class InlineAttributeTest extends \PHPUnit\Framework\TestCase
{
    #[\Deprecated, \Testo\Common\Attribute\AssertMethod] protected function assertInline($value): void
    {
        $this->assertTrue($value);
    }
}
