<?php

#[\Testo\Codecov\Covers(\ArrayObject::class)]
final class ClassLevelCoversTest
{
    #[\Testo\Test]
    public function counts(): void
    {
    }
}
-----
<?php

#[\Testo\Codecov\Covers(\ArrayObject::class)]
#[\Testo\Test]
final class ClassLevelCoversTest
{
    public function counts(): void
    {
    }
}
