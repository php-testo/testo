<?php

final class ExpectAttrSeveralTest
{
    #[\Testo\Test]
    public function rejectsEmptyName(): never
    {
        \Testo\Expect::exception(\InvalidArgumentException::class);
        $value = '';
        new \ArrayObject($value);
    }
}
