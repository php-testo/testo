<?php

class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(failureThreshold: 2)]
    public function noTimes()
    {
    }

    #[\PHPUnit\Framework\Attributes\Repeat(times: 5, maxFailures: 1)]
    public function unknownName()
    {
    }

    #[\PHPUnit\Framework\Attributes\Retry(3, 100)]
    public function extraArgument()
    {
    }
}
