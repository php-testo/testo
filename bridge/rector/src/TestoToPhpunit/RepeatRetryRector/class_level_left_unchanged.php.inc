<?php

// Only method-level attributes are converted: PHPUnit's Repeat/Retry are TARGET_METHOD only, so a
// class-level Testo #[Repeat] has no faithful PHPUnit counterpart and is left untouched.
#[\Testo\Repeat(times: 3)]
class SomeTest
{
    public function test(): void
    {
    }
}
