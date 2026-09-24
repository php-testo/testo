<?php

class SomeTest
{
    public function test()
    {
        $dep->expects($this->once())->method('run')->with('x')->willReturn('y');
        $dep->expects($this->never())->method('a');
        $dep->expects($this->exactly(3))->method('b')->willReturn('b');
        $dep->expects($this->any())->method('c')->willReturn('c');
        $dep->expects($this->atLeastOnce())->method('d')->willReturn('d');
        $dep->expects($this->atLeast(2))->method('e')->willReturn('e');
        $dep->expects(self::atMost(5))->method('f')->willReturn('f');
        $dep->expects($this->once())->method('g')->withAnyParameters()->willReturn('g');
        $this->createMock(Dependency::class)->method('run')->willReturn('y');
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('run')->once()->with('x')->andReturn('y');
        $dep->shouldReceive('a')->never();
        $dep->shouldReceive('b')->times(3)->andReturn('b');
        $dep->shouldReceive('c')->andReturn('c');
        $dep->shouldReceive('d')->atLeast()->once()->andReturn('d');
        $dep->shouldReceive('e')->atLeast()->times(2)->andReturn('e');
        $dep->shouldReceive('f')->atMost()->times(5)->andReturn('f');
        $dep->shouldReceive('g')->once()->andReturn('g');
        \Mockery::mock(Dependency::class)->shouldIgnoreMissing()->shouldReceive('run')->andReturn('y');
    }
}
