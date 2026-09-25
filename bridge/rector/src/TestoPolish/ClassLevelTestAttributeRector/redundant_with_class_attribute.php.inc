<?php

#[\Testo\Test]
final class ClassLevelRedundantTest
{
    #[\Testo\Test]
    public function counts(): void
    {
    }

    #[\Testo\Test]
    public function returnsValue(): int
    {
        return 1;
    }
}
-----
<?php

#[\Testo\Test]
final class ClassLevelRedundantTest
{
    public function counts(): void
    {
    }

    #[\Testo\Test]
    public function returnsValue(): int
    {
        return 1;
    }
}
