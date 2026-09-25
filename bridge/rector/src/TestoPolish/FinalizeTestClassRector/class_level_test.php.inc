<?php

#[\Testo\Test]
class FinalizeClassLevelTest
{
    public function createsOrder(): void
    {
    }
}
-----
<?php

#[\Testo\Test]
final class FinalizeClassLevelTest
{
    public function createsOrder(): void
    {
    }
}
