<?php

class SomeTest
{
    public function direct(): never
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessagePattern('/boom/');
        throw new \RuntimeException('boom');
    }

    public function inChain(): never
    {
        \Testo\Expect::exception(\RuntimeException::class)
            ->withCode(1)
            ->withMessagePattern('/boom/')
            ->withMessageContaining('bo');
        throw new \RuntimeException('boom', 1);
    }

    public function throughVariable(): never
    {
        $expectation = \Testo\Expect::exception(\RuntimeException::class);
        $expectation->withMessagePattern('/boom/');
        throw new \RuntimeException('boom');
    }

    public function typedParameter(\Testo\Assert\Api\ExpectedException $expectation): void
    {
        $expectation->withMessagePattern('/boom/');
    }
}
-----
<?php

class SomeTest
{
    public function direct(): never
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessageMatchingRegex('/boom/');
        throw new \RuntimeException('boom');
    }

    public function inChain(): never
    {
        \Testo\Expect::exception(\RuntimeException::class)
            ->withCode(1)
            ->withMessageMatchingRegex('/boom/')
            ->withMessageContaining('bo');
        throw new \RuntimeException('boom', 1);
    }

    public function throughVariable(): never
    {
        $expectation = \Testo\Expect::exception(\RuntimeException::class);
        $expectation->withMessageMatchingRegex('/boom/');
        throw new \RuntimeException('boom');
    }

    public function typedParameter(\Testo\Assert\Api\ExpectedException $expectation): void
    {
        $expectation->withMessageMatchingRegex('/boom/');
    }
}
