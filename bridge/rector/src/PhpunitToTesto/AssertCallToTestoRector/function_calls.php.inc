<?php

namespace AssertFunctions;

use function PHPUnit\Framework\assertSame;

class FunctionCallsTest
{
    public function test()
    {
        assertSame(3, $result);
        \PHPUnit\Framework\assertTrue($flag);
        assertSomething($result);
    }
}
-----
<?php

namespace AssertFunctions;

use function PHPUnit\Framework\assertSame;

class FunctionCallsTest
{
    public function test()
    {
        \Testo\Assert::same($result, 3);
        \Testo\Assert::true($flag);
        assertSomething($result);
    }
}
