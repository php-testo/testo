<?php

abstract class ExtendsBaseTestCase extends \PHPUnit\Framework\TestCase
{
    public function testFromBase(): void
    {
    }
}

final class ExtendsChildTest extends ExtendsBaseTestCase
{
    #[\Override]
    public function testFromBase(): void
    {
    }

    public function testlowercase(): void
    {
    }

    public function helper(): void
    {
    }
}
-----
<?php

abstract class ExtendsBaseTestCase
{
    #[\Testo\Test]
    public function testFromBase(): void
    {
    }
}

final class ExtendsChildTest extends ExtendsBaseTestCase
{
    #[\Override]
    #[\Testo\Test]
    public function testFromBase(): void
    {
    }

    #[\Testo\Test]
    public function testlowercase(): void
    {
    }

    public function helper(): void
    {
    }
}
