<?php

trait TraitAdaptedByUser
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
    }
}

trait TraitWithoutUsers
{
    #[\Testo\Test]
    public function orphanScenario(): void
    {
    }
}

#[\Testo\Test]
final class TraitAdaptingUserTest
{
    use TraitAdaptedByUser {
        sharedScenario as protected;
    }
}
