<?php

final class ParentBehaviourSetTest extends \PHPUnit\Framework\TestCase
{
    public function testSkipped(): void
    {
        parent::markTestSkipped('not ready');
    }

    public function testThrows(): void
    {
        parent::expectException(\RuntimeException::class);
        throw new \RuntimeException();
    }

    public function testGuarded(): void
    {
        if (\PHP_INT_SIZE === 4) {
            parent::markTestIncomplete('32-bit');
        }
    }
}
-----
<?php

final class ParentBehaviourSetTest
{
    #[\Testo\Test]
    #[\Testo\Skip('not ready')]
    public function testSkipped(): void
    {
    }

    #[\Testo\Test]
    public function testThrows(): void
    {
        \Testo\Expect::exception(\RuntimeException::class);
        throw new \RuntimeException();
    }

    #[\Testo\Test]
    public function testGuarded(): void
    {
        if (\PHP_INT_SIZE === 4) {
            throw new \Testo\Core\Exception\SkipTest('Incomplete: 32-bit');
        }
    }
}
