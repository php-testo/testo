<?php

class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(failureThreshold: 2, times: 5)]
    public function repeated()
    {
    }

    #[\PHPUnit\Framework\Attributes\Repeat(3, failureThreshold: 3)]
    public function mixed()
    {
    }

    #[\PHPUnit\Framework\Attributes\Retry(maxAttempts: 4)]
    public function retried()
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Repeat(times: 5, maxFailures: 1)]
    public function repeated()
    {
    }

    #[\Testo\Repeat(times: 3, maxFailures: 2)]
    public function mixed()
    {
    }

    #[\Testo\Retry(maxAttempts: 4)]
    public function retried()
    {
    }
}
