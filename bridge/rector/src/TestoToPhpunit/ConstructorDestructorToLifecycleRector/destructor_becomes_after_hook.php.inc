<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function __destruct()
    {
        $this->cleanup();
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\After]
    protected function tearDownFromDestructor(): void
    {
        $this->cleanup();
    }
}
