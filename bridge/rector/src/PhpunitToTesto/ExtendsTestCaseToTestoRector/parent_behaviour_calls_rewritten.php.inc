<?php

final class ParentBehaviourTest extends \PHPUnit\Framework\TestCase
{
    public function testSkipped(): void
    {
        parent::markTestSkipped('not ready');
    }

    public function testThrows(): void
    {
        parent::expectException(\RuntimeException::class);
        parent::expectExceptionMessage('boom');
        throw new \RuntimeException('boom');
    }

    public function testCounted(): void
    {
        parent::addToAssertionCount(1);
    }
}
-----
<?php

final class ParentBehaviourTest
{
    #[\Testo\Test]
    public function testSkipped(): void
    {
        self::markTestSkipped('not ready');
    }

    #[\Testo\Test]
    public function testThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        throw new \RuntimeException('boom');
    }

    #[\Testo\Test]
    public function testCounted(): void
    {
        $this->addToAssertionCount(1);
    }
}
