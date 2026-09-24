<?php

use Mockery\Adapter\Phpunit\MockeryTestCase;

final class SomeTest extends MockeryTestCase
{
    public function testRun(): void
    {
    }
}
-----
<?php

use Mockery\Adapter\Phpunit\MockeryTestCase;

final class SomeTest extends \PHPUnit\Framework\TestCase
{
    use \JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
    public function testRun(): void
    {
    }
}
