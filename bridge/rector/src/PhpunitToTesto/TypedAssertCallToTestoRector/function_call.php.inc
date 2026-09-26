<?php

namespace TypedAssertFunctions;

use function PHPUnit\Framework\assertArrayHasKey;

class TypedFunctionCallTest
{
    public function test()
    {
        assertArrayHasKey('id', $row);
    }
}
-----
<?php

namespace TypedAssertFunctions;

use function PHPUnit\Framework\assertArrayHasKey;

class TypedFunctionCallTest
{
    public function test()
    {
        \Testo\Assert::array($row)->hasKeys('id');
    }
}
