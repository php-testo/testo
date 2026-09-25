<?php

final class ExpectAttrHelperTest
{
    public function expectRejection(): never
    {
        \Testo\Expect::exception(\InvalidArgumentException::class);
        new \ArrayObject('');
    }
}
