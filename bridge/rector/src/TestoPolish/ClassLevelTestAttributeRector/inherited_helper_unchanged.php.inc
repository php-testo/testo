<?php

abstract class ClassLevelInheritedBase
{
    public function assertValid(): void
    {
    }
}

final class ClassLevelInheritedTest extends ClassLevelInheritedBase
{
    #[\Testo\Test]
    public function counts(): void
    {
    }
}
