<?php

namespace TraitTestMethods;

trait CountableLimitTestTrait
{
    public static function dataLimits(): array
    {
        return [];
    }

    public function testInitWithLimits(): void
    {
    }

    /**
     * @test
     */
    public function annotated(): void
    {
    }

    abstract public function testImplementedByClass(): void;

    private function testHelper(): void
    {
    }
}
-----
<?php

namespace TraitTestMethods;

trait CountableLimitTestTrait
{
    public static function dataLimits(): array
    {
        return [];
    }

    #[\Testo\Test]
    public function testInitWithLimits(): void
    {
    }

    #[\Testo\Test]
    public function annotated(): void
    {
    }

    abstract public function testImplementedByClass(): void;

    private function testHelper(): void
    {
    }
}
