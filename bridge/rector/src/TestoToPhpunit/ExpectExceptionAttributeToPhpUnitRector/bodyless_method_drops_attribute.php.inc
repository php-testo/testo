<?php

abstract class SomeTest
{
    #[\Testo\Assert\ExpectException(\RuntimeException::class)]
    abstract public function rejectsBadInput(): void;
}
-----
<?php

abstract class SomeTest
{
    abstract public function rejectsBadInput(): void;
}
