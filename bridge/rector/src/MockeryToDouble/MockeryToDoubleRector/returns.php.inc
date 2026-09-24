<?php

class SomeTest
{
    public function test(array $values, array $exceptions)
    {
        $mock->shouldReceive('a')->andReturns(1);
        $mock->shouldReceive('b')->andReturn();
        $mock->shouldReceive('c')->andReturnValues([1, 2]);
        $mock->shouldReceive('d')->andReturnNull();
        $mock->shouldReceive('e')->andReturnFalse();
        $mock->shouldReceive('f')->andReturnSelf();
        $this->mock->shouldReceive('g')->andReturnSelf();
        $mock->shouldReceive('h')->andReturnArg(1);
        $mock->shouldReceive('i')->andReturnUsing(fn ($x) => $x * 2);
        $mock->shouldReceive('j')->andThrow(new \RuntimeException('boom'));
        $mock->shouldReceive('k')->andThrows($exception);
        $mock->shouldReceive('l')->andThrow(\RuntimeException::class, 'boom', 5);
        $mock->shouldReceive('m')->andThrowExceptions([new \LogicException(), $other]);
        $mock->shouldReceive('n')->andReturnValues($values);
        $mock->shouldReceive('o')->andThrowExceptions($exceptions);
        $mock->shouldReceive('p')->andThrow('RuntimeException', 'boom');
        $mock->shouldReceive('q')->andThrow($exception, 'ignored', 0);
    }
}
-----
<?php

class SomeTest
{
    public function test(array $values, array $exceptions)
    {
        $mock->allows('a')->returns(1);
        $mock->allows('b')->returns(null);
        $mock->allows('c')->returns(1, 2);
        $mock->allows('d')->returns(null);
        $mock->allows('e')->returns(false);
        $mock->allows('f')->returns($mock);
        $this->mock->allows('g')->returns($this->mock);
        $mock->allows('h')->resolves(fn(...$args) => $args[1]);
        $mock->allows('i')->resolves(fn ($x) => $x * 2);
        $mock->allows('j')->throws(new \RuntimeException('boom'));
        $mock->allows('k')->throws($exception);
        $mock->allows('l')->throws(new \RuntimeException('boom', 5));
        $mock->allows('m')->throws(new \LogicException(), $other);
        $mock->allows('n')->returns(...$values);
        $mock->allows('o')->throws(...$exceptions);
        $mock->allows('p')->throws(new \RuntimeException('boom'));
        $mock->allows('q')->throws($exception);
    }
}
