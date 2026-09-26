<?php

final class ParentCallsTest extends \PHPUnit\Framework\TestCase
{
    private static int $booted = 0;
    private int $value = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$booted++;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->value = 1;

        $this->value++;
    }

    protected function tearDown(): void
    {
        $this->value = 0;

        parent::tearDown();
    }

    protected function assertPostConditions(): void
    {
        if ($this->value > 0) {
            parent::assertPostConditions();
        }

        $result = parent::getName();
        $helper = new class extends \ArrayObject {
            public function count(): int
            {
                parent::count();

                return 0;
            }
        };
    }

    public function testFoo(): void
    {
        \assert($this->value === 2);
    }
}
-----
<?php

final class ParentCallsTest
{
    private static int $booted = 0;
    private int $value = 0;

    public static function setUpBeforeClass(): void
    {
        self::$booted++;
    }

    protected function setUp(): void
    {
        $this->value = 1;

        $this->value++;
    }

    protected function tearDown(): void
    {
        $this->value = 0;
    }

    protected function assertPostConditions(): void
    {
        if ($this->value > 0) {
        }

        $result = parent::getName();
        $helper = new class extends \ArrayObject {
            public function count(): int
            {
                parent::count();

                return 0;
            }
        };
    }

    #[\Testo\Test]
    public function testFoo(): void
    {
        \assert($this->value === 2);
    }
}
