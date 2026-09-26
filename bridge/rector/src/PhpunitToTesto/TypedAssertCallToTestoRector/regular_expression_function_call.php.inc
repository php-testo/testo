<?php

namespace TypedAssertRegularExpressionFunctions;

use function PHPUnit\Framework\assertDoesNotMatchRegularExpression;
use function PHPUnit\Framework\assertMatchesRegularExpression;

class RegularExpressionFunctionCallTest
{
    public function test()
    {
        assertMatchesRegularExpression('/^id-\d+$/', $id, 'id format');
        assertDoesNotMatchRegularExpression('/\s/', $id);
    }
}
-----
<?php

namespace TypedAssertRegularExpressionFunctions;

use function PHPUnit\Framework\assertDoesNotMatchRegularExpression;
use function PHPUnit\Framework\assertMatchesRegularExpression;

class RegularExpressionFunctionCallTest
{
    public function test()
    {
        \Testo\Assert::string($id)->matchesRegex('/^id-\d+$/', 'id format');
        \Testo\Assert::string($id)->notMatchesRegex('/\s/');
    }
}
