<?php

class SomeTest
{
    public function test()
    {
        $computedIntersection = $this->createMockForIntersectionOfInterfaces($interfaces);
        $computedMap = $this->createConfiguredMock(Dependency::class, $map);
        $listMap = $this->createConfiguredMock(Dependency::class, [1, 2]);
        $emptyMap = $this->createConfiguredMock(Dependency::class, []);
        $computedPartial = $this->createPartialMock(Dependency::class, $methods);
        $inlineConfigured = new Service($this->createConfiguredMock(Dependency::class, ['run' => 1]));
        $this->mocks[] = $this->createPartialMock(Dependency::class, ['run']);
        $prophecy = $this->prophesize(Dependency::class);
        $other = $factory->createMock(Dependency::class);
        $abstract = $this->getMockForAbstractClass(Dependency::class);
    }
}
