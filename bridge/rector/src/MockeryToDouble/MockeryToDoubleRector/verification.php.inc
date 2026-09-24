<?php

class SomeTest
{
    public function test(array $args)
    {
        $spy->shouldHaveReceived('run');
        $spy->shouldHaveReceived('run')->with('x')->once();
        $spy->shouldHaveReceived('run', ['x', 2]);
        $spy->shouldHaveReceived('run')->times(3);
        $spy->shouldHaveReceived()->run('x');
        $spy->shouldHaveReceived()->run();
        $spy->shouldNotHaveReceived('run');
        $spy->shouldNotHaveReceived('run', ['x']);
        $spy->shouldNotHaveReceived()->run('x');
        $spy->shouldHaveReceived('run', fn ($x) => $x > 1);
        $spy->shouldHaveReceived('run', $args)->twice();
        $spy->shouldHaveReceived('run')->withArgs([\Mockery::any()])->atLeast()->once();
        $callable->shouldHaveBeenCalled();
        $callable->shouldNotHaveBeenCalled();
        $callable->shouldNotHaveBeenCalled([1]);
        \Mockery::close();
    }
}
-----
<?php

class SomeTest
{
    public function test(array $args)
    {
        $spy->received('run');
        $spy->received('run')->with('x')->times(1);
        $spy->received('run')->with('x', 2);
        $spy->received('run')->times(3);
        $spy->received('run')->with('x');
        $spy->received('run')->with(\JMac\Testing\Matching\Argument::none());
        $spy->received('run')->never();
        $spy->received('run')->with('x')->never();
        $spy->received('run')->with('x')->never();
        $spy->received('run')->with(\JMac\Testing\Matching\Argument::all(fn ($x) => $x > 1));
        $spy->received('run')->with(...$args)->times(2);
        $spy->received('run')->with(\JMac\Testing\Matching\Argument::any())->times(minimum: 1);
        $callable->received('__invoke');
        $callable->received('__invoke')->never();
        $callable->received('__invoke')->with(1)->never();
        \JMac\Testing\Double::verifyAll();
    }
}
