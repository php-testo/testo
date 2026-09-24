<?php

class SomeTest
{
    public function test()
    {
        $neutral = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->disableOriginalClone()->disableArgumentCloning()->getMock();
        $strict = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->disableAutoReturnValueGeneration()->getMock();
        $partial = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run', 'stop'])->getMock();
        $this->constructed = $this->getMockBuilder(Dependency::class)->onlyMethods(['run'])->getMock();
        $withArgs = $this->getMockBuilder(Dependency::class)->setConstructorArgs([1, 'x'])->onlyMethods(['run'])->getMock();
        $computedArgs = $this->getMockBuilder(Dependency::class)->setConstructorArgs($args)->onlyMethods(['run'])->getMock();
        $real = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $service = new Service($this->getMockBuilder(Dependency::class)->setConstructorArgs([1])->onlyMethods([])->getMock());
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $neutral = \JMac\Testing\Double::for(Dependency::class);
        $strict = \JMac\Testing\Double::for(Dependency::class)->strict();
        $partial = \JMac\Testing\Double::for(Dependency::class)->passthru();
        $partial->allows('run');
        $partial->allows('stop');
        $this->constructed = \JMac\Testing\Double::for(Dependency::class)->passthru(new Dependency());
        $this->constructed->allows('run');
        $withArgs = \JMac\Testing\Double::for(Dependency::class)->passthru(new Dependency(1, 'x'));
        $withArgs->allows('run');
        $computedArgs = \JMac\Testing\Double::for(Dependency::class)->passthru(new Dependency(...$args));
        $computedArgs->allows('run');
        $real = \JMac\Testing\Double::for(Dependency::class)->passthru();
        $service = new Service(\JMac\Testing\Double::for(Dependency::class)->passthru(new Dependency(1)));
    }
}
