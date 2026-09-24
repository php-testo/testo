<?php

class SomeTest
{
    public function test()
    {
        $plain = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->getMock();
        $neutral = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->disableOriginalClone()->disableArgumentCloning()->getMock();
        $strict = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->disableAutoReturnValueGeneration()->getMock();
        $partial = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run'])->getMock();
        $constructed = $this->getMockBuilder(Dependency::class)->onlyMethods(['run'])->getMock();
        $withArgs = $this->getMockBuilder(Dependency::class)->setConstructorArgs([1, 'x'])->onlyMethods(['run'])->getMock();
        $strictPartial = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run'])->disableAutoReturnValueGeneration()->getMock();
        $real = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $realConstructed = $this->getMockBuilder(Dependency::class)->setConstructorArgs($args)->onlyMethods([])->getMock();
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $plain = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
        $neutral = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
        $strict = \Mockery::mock(Dependency::class);
        $partial = \Mockery::mock('Dependency[run]')->shouldIgnoreMissing();
        $constructed = \Mockery::mock('Dependency[run]', [])->shouldIgnoreMissing();
        $withArgs = \Mockery::mock('Dependency[run]', [1, 'x'])->shouldIgnoreMissing();
        $strictPartial = \Mockery::mock('Dependency[run]');
        $real = \Mockery::mock(Dependency::class)->makePartial();
        $realConstructed = \Mockery::mock(Dependency::class, $args)->makePartial();
    }
}
