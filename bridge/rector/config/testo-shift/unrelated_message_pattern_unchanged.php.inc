<?php

class Matcher
{
    public function withMessagePattern(string $pattern): self
    {
        return $this;
    }
}

class SomeTest
{
    public function test(Matcher $matcher, $unknown): void
    {
        $matcher->withMessagePattern('/boom/');
        $unknown->withMessagePattern('/boom/');
    }
}
