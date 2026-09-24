<?php

class SomeTest
{
    public function test()
    {
        $value = 5;
        $dep->method('n')->with($this->greaterThan($value))->willReturn('u');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $value = 5;
        $dep->shouldReceive('n')->with(\Mockery::on(fn($value2) => $value2 > $value))->andReturn('u');
    }
}
