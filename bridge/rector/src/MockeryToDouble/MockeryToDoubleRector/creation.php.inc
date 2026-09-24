<?php

use Mockery as m;

class SomeTest
{
    public function test(string $className, \App\Real $real)
    {
        $mock = \Mockery::mock(Dependency::class);
        $alias = m::mock(Dependency::class);
        $spy = \Mockery::spy(Dependency::class);
        $loose = \Mockery::mock(Dependency::class)->shouldIgnoreMissing();
        $partial = \Mockery::mock(Dependency::class)->makePartial();
        $constructed = \Mockery::mock(Dependency::class, [1, 'x'])->makePartial();
        $both = \Mockery::mock(First::class, Second::class);
        $named = \Mockery::mock('App\Dependency');
        $list = \Mockery::mock('App\First, App\Second');
        $union = \Mockery::spy('App\First|App\Second');
        $computed = \Mockery::mock($className);
        $proxy = \Mockery::mock($real);
    }
}
-----
<?php

use Mockery as m;

class SomeTest
{
    public function test(string $className, \App\Real $real)
    {
        $mock = \JMac\Testing\Double::for(Dependency::class)->strict();
        $alias = \JMac\Testing\Double::for(Dependency::class)->strict();
        $spy = \JMac\Testing\Double::for(Dependency::class);
        $loose = \JMac\Testing\Double::for(Dependency::class);
        $partial = \JMac\Testing\Double::for(Dependency::class)->passthru();
        $constructed = \JMac\Testing\Double::for(Dependency::class)->passthru(new Dependency(1, 'x'));
        $both = \JMac\Testing\Double::for(First::class, Second::class)->strict();
        $named = \JMac\Testing\Double::for('App\Dependency')->strict();
        $list = \JMac\Testing\Double::for('App\First', 'App\Second')->strict();
        $union = \JMac\Testing\Double::for('App\First', 'App\Second');
        $computed = \JMac\Testing\Double::for($className)->strict();
        $proxy = \JMac\Testing\Double::for($real)->passthru();
    }
}
