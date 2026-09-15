<?php

class SomeTest
{
    #[\Testo\Test]
    public function testA(): void
    {
    }

    #[\Testo\Skip('inert on a helper')]
    public function helper(): void
    {
    }
}
