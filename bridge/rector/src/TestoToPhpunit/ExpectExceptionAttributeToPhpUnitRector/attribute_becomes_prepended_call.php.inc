<?php

class SomeTest
{
    #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
    public function rejectsBadInput(): void
    {
        new Thing('bad');
    }
}
-----
<?php

class SomeTest
{
    public function rejectsBadInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Thing('bad');
    }
}
