<?php

class SomeTest extends \PHPUnit\Framework\TestCase
{
    public function __construct()
    {
        $this->svc = new Service();
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
}
