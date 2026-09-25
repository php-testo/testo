<?php

trait ClassLevelSharedTests
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
    }
}

final class ClassLevelTraitTest
{
    use ClassLevelSharedTests;

    #[\Testo\Test]
    public function counts(): void
    {
    }
}
-----
<?php

trait ClassLevelSharedTests
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
    }
}

#[\Testo\Test]
final class ClassLevelTraitTest
{
    use ClassLevelSharedTests;

    public function counts(): void
    {
    }
}
