<?php

class JsonAssertionsTest
{
    public function test()
    {
        $this->assertJson($body);
        $this->assertJson($body, 'valid payload');

        // PHPUnit compares re-encoded JSON: `1` differs from `1.0` and `{}` from `[]`, which a decoded `==` conflates.
        $this->assertJsonStringEqualsJsonString($expected, $body);
        $this->assertJsonStringNotEqualsJsonString($expected, $body);
        $this->assertJsonStringEqualsJsonFile($file, $body);
        $this->assertJsonFileEqualsJsonFile($expectedFile, $file);
    }
}
-----
<?php

class JsonAssertionsTest
{
    public function test()
    {
        \Testo\Assert::json($body);
        \Testo\Assert::json($body);

        // PHPUnit compares re-encoded JSON: `1` differs from `1.0` and `{}` from `[]`, which a decoded `==` conflates.
        $this->assertJsonStringEqualsJsonString($expected, $body);
        $this->assertJsonStringNotEqualsJsonString($expected, $body);
        $this->assertJsonStringEqualsJsonFile($file, $body);
        $this->assertJsonFileEqualsJsonFile($expectedFile, $file);
    }
}
