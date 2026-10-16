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
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->matchesRegex($pattern, $message);
        })('/^id-\d+$/', $id, 'id format');
        (static function (string $pattern, string $string, string $message = ''): void {
            \Testo\Assert::string($string)->notMatchesRegex($pattern, $message);
        })('/\s/', $id);
    }
}
