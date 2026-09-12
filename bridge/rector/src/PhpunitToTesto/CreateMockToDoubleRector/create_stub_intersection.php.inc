<?php

class SomeTest
{
    public function test()
    {
        $agg = $this->createStubForIntersectionOfInterfaces([A::class, B::class]);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $agg = \JMac\Testing\Double::for(A::class, B::class);
    }
}
