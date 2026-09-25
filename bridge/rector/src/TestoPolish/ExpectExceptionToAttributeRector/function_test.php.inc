<?php

#[\Testo\Test]
function expect_attr_rejects_empty_name(): never
{
    \Testo\Expect::exception(\InvalidArgumentException::class);
    new \ArrayObject('');
}
-----
<?php

#[\Testo\Test]
#[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
function expect_attr_rejects_empty_name(): never
{
    new \ArrayObject('');
}
