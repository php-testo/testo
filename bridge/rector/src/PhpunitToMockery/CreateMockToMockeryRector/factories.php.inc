<?php

namespace App\Tests;

use App\Dependency;

class SomeTest
{
    public function test()
    {
        $mock = $this->createMock(Dependency::class);
        $stub = $this->createStub(Dependency::class);
        $both = $this->createMockForIntersectionOfInterfaces([A::class, B::class]);
        $bothStub = $this->createStubForIntersectionOfInterfaces([A::class, B::class]);
        $configured = $this->createConfiguredMock(Dependency::class, ['run' => 1, 'stop' => $value]);
        $configuredStub = $this->createConfiguredStub(Dependency::class, ['run' => 1]);
        $partial = $this->createPartialMock(Dependency::class, ['run', 'stop']);
        $allReal = $this->createPartialMock(Dependency::class, []);
        $named = $this->createPartialMock('App\Other', ['run']);
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
        $mock = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
        $stub = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
        $both = \Mockery::mock(A::class, B::class)->shouldIgnoreMissing();
        $bothStub = \Mockery::mock(A::class, B::class)->shouldIgnoreMissing();
        $configured = \Mockery::mock(Dependency::class, ['run' => 1, 'stop' => $value])->shouldIgnoreMissing();
        $configuredStub = \Mockery::mock(Dependency::class, ['run' => 1])->shouldIgnoreMissing();
        $partial = \Mockery::mock('App\Dependency[run,stop]')->shouldIgnoreMissing();
        $allReal = \Mockery::mock(Dependency::class)->makePartial();
        $named = \Mockery::mock('App\Other[run]')->shouldIgnoreMissing();
    }
}
