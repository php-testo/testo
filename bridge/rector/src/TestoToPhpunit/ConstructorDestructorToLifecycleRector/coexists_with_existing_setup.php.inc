<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function __construct()
    {
        $this->svc = new Service();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset();
    }
}
-----
<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\Before]
    protected function setUpFromConstructor(): void
    {
        $this->svc = new Service();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reset();
    }
}
