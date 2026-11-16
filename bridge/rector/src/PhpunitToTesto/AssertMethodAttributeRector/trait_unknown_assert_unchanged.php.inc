<?php

trait DatabaseFixtures
{
    protected function assertFixture(): void
    {
        $this->assertDatabaseReady();
    }
}
