<?php

class SomeTest
{
    public function test()
    {
        $double->allows('run')->returns(1);
        $double->expects('run')->with('x')->times(2)->returns(1);
        $double->expects('run')->never();
        $double->received('run')->with('x');
        $builder->with('x')->times(2);
    }
}
