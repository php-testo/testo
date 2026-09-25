<?php

final class ExpectAttrSpecimenTest
{
    #[\Testo\Test]
    public function rejectsEmptyName(): never
    {
        \Testo\Expect::exception(new \InvalidArgumentException('empty'));
        new \ArrayObject('');
    }
}
