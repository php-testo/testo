<?php

final class ExpectAttrSingleTest
{
    #[\Testo\Test]
    public function rejectsEmptyName(): never
    {
        \Testo\Expect::exception(\InvalidArgumentException::class);
        new \ArrayObject('');
    }
}
-----
<?php

final class ExpectAttrSingleTest
{
    #[\Testo\Test]
    #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
    public function rejectsEmptyName(): never
    {
        new \ArrayObject('');
    }
}
