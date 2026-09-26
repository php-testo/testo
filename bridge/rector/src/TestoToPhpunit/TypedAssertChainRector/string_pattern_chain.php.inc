<?php

class StringPatternChainTest
{
    public function test()
    {
        \Testo\Assert::string($date)->matchesRegex('/^\d{4}-\d{2}-\d{2}$/')->notMatchesRegex('/\s$/');
    }

    public static function staticCheck(string $id): void
    {
        \Testo\Assert::string($id)->matchesRegex('/^id-\d+$/i', 'id format');
    }
}
-----
<?php

class StringPatternChainTest
{
    public function test()
    {
        $this->assertIsString($date);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        $this->assertDoesNotMatchRegularExpression('/\s$/', $date);
    }

    public static function staticCheck(string $id): void
    {
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/^id-\d+$/i', $id);
    }
}
