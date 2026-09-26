<?php

final class ClassLevelUntypedHelperTest
{
    #[\Testo\Test]
    public function counts(): void
    {
        $this->check(1);
    }

    public function check(int $x)
    {
    }
}
