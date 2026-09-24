<?php

class SomeTest
{
    public function test()
    {
        $bare = $this->getMockBuilder(Dependency::class)->getMock();
        $constructedFull = $this->getMockBuilder(Dependency::class)->setConstructorArgs([1])->getMock();
        $contradictory = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->setConstructorArgs([1])->onlyMethods(['run'])->getMock();
        $abstract = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->getMockForAbstractClass();
        $added = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->addMethods(['magic'])->getMock();
        $renamed = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->setMockClassName('Fake')->getMock();
        $strictPartial = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run'])->disableAutoReturnValueGeneration()->getMock();
        $computedMethods = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
        $inlinePartial = new Service($this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run'])->getMock());
    }
}
