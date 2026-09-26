<?php

class StopsAtThrowingStatementTest
{
    public function testThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $value = $this->build();
        $this->expectExceptionMessage('boom');

        $value->run();
    }
}
-----
<?php

class StopsAtThrowingStatementTest
{
    public function testThrows(): void
    {
        \Testo\Expect::exception(\RuntimeException::class);
        $value = $this->build();
        $this->expectExceptionMessage('boom');

        $value->run();
    }
}
