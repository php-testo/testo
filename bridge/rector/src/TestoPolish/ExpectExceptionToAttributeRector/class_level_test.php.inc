<?php

#[\Testo\Test]
final class ExpectAttrClassLevelTest
{
    public function rejectsEmptyName(): never
    {
        \Testo\Expect::exception(\InvalidArgumentException::class);
        new \ArrayObject('');
    }
}
-----
<?php

#[\Testo\Test]
final class ExpectAttrClassLevelTest
{
    #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
    public function rejectsEmptyName(): never
    {
        new \ArrayObject('');
    }
}
