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
        $twice = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->disableOriginalConstructor()->getMock();
        $computedMethods = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods($methods)->getMock();
        $computedIntersection = $this->createMockForIntersectionOfInterfaces($interfaces);
        $computedMap = $this->createConfiguredMock(Dependency::class, $map);
        $listMap = $this->createConfiguredMock(Dependency::class, [1, 2]);
        $emptyMap = $this->createConfiguredMock(Dependency::class, []);
        $computedPartial = $this->createPartialMock(Dependency::class, $methods);
        $computedName = $this->createPartialMock(Dependency::class, [$method]);
        $computedClass = $this->createPartialMock($class, ['run']);
        $prophecy = $this->prophesize(Dependency::class);
        $other = $factory->createMock(Dependency::class);
    }
}
