<?php

trait InterceptingAssertions
{
    abstract public static function assertTrue($condition): void;

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    protected function check($value): void
    {
        $this->assertTrue($value);
    }
}
