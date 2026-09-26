<?php

namespace App\Tests;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;

final class ImportMocksTest
{
    private \Mockery\MockInterface $mock;

    #[\Testo\Test]
    public function mockery(): void
    {
        $this->mock = \Mockery::mock(\App\Service::class);
        $this->mock instanceof MockInterface or throw new \App\Failure();
    }

    #[\Testo\Test]
    public function double(): void
    {
        $double = \JMac\Testing\Double::mock(\App\Service::class);
        $double->shouldReceive('run')->with(\JMac\Testing\Matching\Argument::any());

        try {
            \Testo\Internal\helper();
        } catch (\Testo\Core\Exception\SkipTest $e) {
        }
    }
}
-----
<?php

namespace App\Tests;

use Testo\Test;
use Mockery;
use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;
use Testo\Core\Exception\SkipTest;
use Mockery\MockInterface;

final class ImportMocksTest
{
    private MockInterface $mock;

    #[Test]
    public function mockery(): void
    {
        $this->mock = Mockery::mock(\App\Service::class);
        $this->mock instanceof MockInterface or throw new \App\Failure();
    }

    #[Test]
    public function double(): void
    {
        $double = Double::mock(\App\Service::class);
        $double->shouldReceive('run')->with(Argument::any());

        try {
            \Testo\Internal\helper();
        } catch (SkipTest $e) {
        }
    }
}
