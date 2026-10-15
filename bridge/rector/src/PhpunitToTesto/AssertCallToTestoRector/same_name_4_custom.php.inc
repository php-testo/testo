<?php

class SameNameSecondTest
{
    public function assertSame($expected, $actual): string { return 'custom'; }
    public function run(): string { return $this->assertSame(1, 2); }
}
