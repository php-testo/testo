<?php

class SomeTest
{
    public function test()
    {
        \Testo\Assert::callable($handler);
        \Testo\Assert::callable($this->factory());
        \Testo\Assert::string($name);
    }

    public static function provider()
    {
        \Testo\Assert::callable($handler);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $this->assertIsCallable($handler);
        $this->assertIsCallable($this->factory());
        $this->assertIsString($name);
    }

    public static function provider()
    {
        self::assertIsCallable($handler);
    }
}
