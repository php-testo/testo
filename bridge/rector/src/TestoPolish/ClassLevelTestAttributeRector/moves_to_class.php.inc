<?php

final class ClassLevelMoveTest
{
    #[\Testo\Lifecycle\BeforeTest]
    public function setUp(): void
    {
    }

    #[\Testo\Test]
    public function createsOrder(): void
    {
    }

    #[\Testo\Test, \Testo\Data\DataProvider('orders')]
    public function cancelsOrder(int $id): never
    {
        throw new \RuntimeException();
    }

    public static function orders(): iterable
    {
        yield [1];
    }

    private function helper(): void
    {
    }
}
-----
<?php

#[\Testo\Test]
final class ClassLevelMoveTest
{
    #[\Testo\Lifecycle\BeforeTest]
    public function setUp(): void
    {
    }

    public function createsOrder(): void
    {
    }

    #[\Testo\Data\DataProvider('orders')]
    public function cancelsOrder(int $id): never
    {
        throw new \RuntimeException();
    }

    public static function orders(): iterable
    {
        yield [1];
    }

    private function helper(): void
    {
    }
}
