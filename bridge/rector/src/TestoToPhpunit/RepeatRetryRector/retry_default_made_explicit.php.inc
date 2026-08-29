<?php

// A bare #[Retry] carries Testo's default maxAttempts (3); PHPUnit's Retry requires it, so it is
// emitted explicitly.
class SomeTest
{
    #[\Testo\Retry]
    public function test(): void
    {
    }
}
-----
<?php

// A bare #[Retry] carries Testo's default maxAttempts (3); PHPUnit's Retry requires it, so it is
// emitted explicitly.
class SomeTest
{
    #[\PHPUnit\Framework\Attributes\Retry(3)]
    public function test(): void
    {
    }
}
