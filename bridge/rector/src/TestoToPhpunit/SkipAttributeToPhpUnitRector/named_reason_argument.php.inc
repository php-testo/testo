<?php

class SomeTest
{
    #[\Testo\Test]
    #[\Testo\Skip(reason: 'flaky on CI, see ISSUE-123')]
    public function testA(): void
    {
    }
}
-----
<?php

class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
        $this->markTestSkipped('flaky on CI, see ISSUE-123');
    }
}
