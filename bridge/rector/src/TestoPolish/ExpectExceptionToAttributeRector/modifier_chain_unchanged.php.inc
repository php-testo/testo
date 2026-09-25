<?php

final class ExpectAttrChainTest
{
    #[\Testo\Test]
    public function rejectsEmptyName(): never
    {
        \Testo\Expect::exception(\InvalidArgumentException::class)->withMessageContaining('empty');
        new \ArrayObject('');
    }
}
