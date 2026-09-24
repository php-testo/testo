<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class SomeTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use OtherTrait, \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    public function testRun(): void
    {
    }
}
-----
<?php

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

final class SomeTest extends TestCase
{
    use \JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;
    use OtherTrait, \JMac\Testing\Integrations\PHPUnit\VerifiesDoubles;

    public function testRun(): void
    {
    }
}
