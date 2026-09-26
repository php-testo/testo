<?php

trait InnerSharedTests
{
    #[\Testo\Test]
    public function innerScenario(): void
    {
    }
}

trait OuterSharedTests
{
    use InnerSharedTests;
}

#[\Testo\Test]
final class TraitThroughTraitTest
{
    use OuterSharedTests;
}
-----
<?php

trait InnerSharedTests
{
    public function innerScenario(): void
    {
    }
}

trait OuterSharedTests
{
    use InnerSharedTests;
}

#[\Testo\Test]
final class TraitThroughTraitTest
{
    use OuterSharedTests;
}
