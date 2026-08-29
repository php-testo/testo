<?php

// failureThreshold 1 (PHPUnit default) maps to maxFailures 0 (Testo default) and is omitted.
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Repeat(4, 1)]
    public function test()
    {
    }
}
-----
<?php

// failureThreshold 1 (PHPUnit default) maps to maxFailures 0 (Testo default) and is omitted.
class SomeTest
{
    #[\Testo\Repeat(times: 4)]
    public function test()
    {
    }
}
