<?php

class FooTest
{
    #[\PHPUnit\Framework\Attributes\DataProvider(methodName: 'rows')]
    #[\PHPUnit\Framework\Attributes\DataProvider('more', validateArgumentCount: false)]
    #[\PHPUnit\Framework\Attributes\DataProvider(skipWhenEmpty: true, methodName: 'maybeEmpty')]
    #[\PHPUnit\Framework\Attributes\DataProvider(method: 'unknown')]
    public function test($value)
    {
    }
}
-----
<?php

class FooTest
{
    #[\Testo\Data\DataProvider('rows')]
    #[\Testo\Data\DataProvider('more')]
    #[\Testo\Data\DataProvider('maybeEmpty')]
    #[\PHPUnit\Framework\Attributes\DataProvider(method: 'unknown')]
    public function test($value)
    {
    }
}
