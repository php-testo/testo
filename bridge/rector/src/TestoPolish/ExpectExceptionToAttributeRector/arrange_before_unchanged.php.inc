<?php

final class ExpectAttrArrangeTest
{
    #[\Testo\Test]
    public function rejectsEmptyName(): never
    {
        $value = '';
        \Testo\Expect::exception(\InvalidArgumentException::class);
        new \ArrayObject($value);
    }
}
