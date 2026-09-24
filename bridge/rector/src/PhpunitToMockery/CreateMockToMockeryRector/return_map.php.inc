<?php

class SomeTest
{
    public function test()
    {
        $dep->method('run')->willReturnMap([['a', 1], ['b', 'c', 2]]);
        $dep->expects($this->exactly(2))->method('map')->willReturnMap($map);
        $dep->method('legacy')->will($this->returnValueMap([['a', 1]]));
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        $dep->shouldReceive('run')->andReturnUsing(fn(...$args) => array_column(array_filter([['a', 1], ['b', 'c', 2]], fn($row) => array_slice($row, 0, -1) === $args), count($args))[0] ?? null);
        $dep->shouldReceive('map')->times(2)->andReturnUsing(fn(...$args) => array_column(array_filter($map, fn($row) => array_slice($row, 0, -1) === $args), count($args))[0] ?? null);
        $dep->shouldReceive('legacy')->andReturnUsing(fn(...$args) => array_column(array_filter([['a', 1]], fn($row) => array_slice($row, 0, -1) === $args), count($args))[0] ?? null);
    }
}
