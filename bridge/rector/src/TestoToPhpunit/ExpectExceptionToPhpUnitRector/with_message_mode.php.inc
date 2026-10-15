<?php

class MessageModeTest
{
    public function substring(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('boom', exact: false);
    }

    public function emptySubstring(): void
    {
        \Testo\Expect::exception(\RuntimeException::class)->withMessage('', exact: false);
    }
}
-----
<?php

class MessageModeTest
{
    public function substring(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
    }

    public function emptySubstring(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('~~');
    }
}
