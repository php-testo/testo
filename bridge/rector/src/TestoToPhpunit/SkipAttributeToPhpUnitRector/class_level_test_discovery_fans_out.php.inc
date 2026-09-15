<?php

#[\Testo\Test]
#[\Testo\Skip('case reason')]
class SomeTest
{
    public function testA(): void
    {
    }

    public function helper(): string
    {
        return 'x';
    }
}
-----
<?php

#[\Testo\Test]
class SomeTest
{
    public function testA(): void
    {
        $this->markTestSkipped('case reason');
    }

    public function helper(): string
    {
        return 'x';
    }
}
