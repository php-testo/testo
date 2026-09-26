<?php

trait TraitWithMethodLevelUser
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
    }
}

#[\Testo\Test]
final class TraitMixedClassLevelUserTest
{
    use TraitWithMethodLevelUser;
}

class TraitMethodLevelUserTest
{
    use TraitWithMethodLevelUser;

    protected function helper(): void
    {
    }
}
