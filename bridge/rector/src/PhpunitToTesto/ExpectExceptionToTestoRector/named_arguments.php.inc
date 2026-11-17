<?php

class SomeTest
{
    public function test()
    {
        $this->expectException(exception: \RuntimeException::class);
        $this->expectExceptionMessage(message: 'boom');
        $this->expectExceptionCode(code: 7);
    }

    public function pattern()
    {
        self::expectException(exception: \LogicException::class);
        self::expectExceptionMessageMatches(regularExpression: '/^bo+m$/');
    }

    public function unknownModifierName()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(text: 'boom');
    }

    public function unknownHeadName()
    {
        $this->expectException(class: \RuntimeException::class);
    }
}
-----
<?php

class SomeTest
{
    public function test()
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageContaining('boom')->withCode(7);
    }

    public function pattern()
    {
        \Testo\Expect::exception(\LogicException::class)->withMessageMatchingRegex('/^bo+m$/');
    }

    public function unknownModifierName()
    {
        \Testo\Expect::exception(\RuntimeException::class);
        $this->expectExceptionMessage(text: 'boom');
    }

    public function unknownHeadName()
    {
        $this->expectException(class: \RuntimeException::class);
    }
}
