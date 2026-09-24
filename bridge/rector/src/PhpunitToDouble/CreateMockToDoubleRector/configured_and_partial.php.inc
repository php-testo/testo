<?php

namespace App\Tests;

use App\Dependency;

class SomeTest
{
    public function test()
    {
        $configured = $this->createConfiguredMock(Dependency::class, ['run' => 1, 'stop' => $value]);
        $this->stub = $this->createConfiguredStub(Dependency::class, ['run' => 1]);
        $partial = $this->createPartialMock(Dependency::class, ['run', 'stop']);
        $allReal = $this->createPartialMock(Dependency::class, []);
        $named = $this->createPartialMock('App\Other', ['run']);
        $partial->method('run')->willReturn(5);
    }
}
-----
<?php

namespace App\Tests;

use App\Dependency;

class SomeTest
{
    public function test()
    {
        $configured = \JMac\Testing\Double::for(Dependency::class);
        $configured->allows('run')->returns(1);
        $configured->allows('stop')->returns($value);
        $this->stub = \JMac\Testing\Double::for(Dependency::class);
        $this->stub->allows('run')->returns(1);
        $partial = \JMac\Testing\Double::for(Dependency::class)->passthru();
        $partial->allows('run');
        $partial->allows('stop');
        $allReal = \JMac\Testing\Double::for(Dependency::class)->passthru();
        $named = \JMac\Testing\Double::for('App\Other')->passthru();
        $named->allows('run');
        $partial->allows('run')->returns(5);
    }
}
