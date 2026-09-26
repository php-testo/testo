<?php

trait TraitOfClassLevelUsers
{
    #[\Testo\Test]
    public function sharedScenario(): void
    {
    }

    #[\Testo\Test]
    public function dataShared(): iterable
    {
        return [];
    }
}

#[\Testo\Test]
final class TraitClassLevelUserTest
{
    use TraitOfClassLevelUsers;
}

#[\Testo\Test]
abstract class TraitClassLevelBase
{
}

final class TraitInheritedClassLevelUserTest extends TraitClassLevelBase
{
    use TraitOfClassLevelUsers;
}
-----
<?php

trait TraitOfClassLevelUsers
{
    public function sharedScenario(): void
    {
    }

    #[\Testo\Test]
    public function dataShared(): iterable
    {
        return [];
    }
}

#[\Testo\Test]
final class TraitClassLevelUserTest
{
    use TraitOfClassLevelUsers;
}

#[\Testo\Test]
abstract class TraitClassLevelBase
{
}

final class TraitInheritedClassLevelUserTest extends TraitClassLevelBase
{
    use TraitOfClassLevelUsers;
}
