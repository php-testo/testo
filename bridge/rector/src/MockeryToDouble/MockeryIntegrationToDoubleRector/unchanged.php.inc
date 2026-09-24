<?php

use Mockery\Adapter\Phpunit\MockeryTestCase;

final class HookedTest extends MockeryTestCase
{
    protected function mockeryTestSetUp(): void
    {
    }
}

final class AdaptedTest extends \PHPUnit\Framework\TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration {
        mockeryAssertPostConditions as protected assertMocks;
    }
}

final class PlainTest extends \PHPUnit\Framework\TestCase
{
    use SomeTrait;
}
