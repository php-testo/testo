<?php

class SomeTest
{
    public function test()
    {
        $bare = $this->getMockBuilder(Dependency::class)->getMock();
        $partial = $this->getMockBuilder(Dependency::class)->disableOriginalConstructor()->onlyMethods(['run'])->getMock();
    }
}
