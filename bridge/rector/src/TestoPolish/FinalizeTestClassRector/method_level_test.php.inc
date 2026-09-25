<?php

class FinalizeOrderTest
{
    #[\Testo\Test]
    public function createsOrder(): void
    {
    }
}
-----
<?php

final class FinalizeOrderTest
{
    #[\Testo\Test]
    public function createsOrder(): void
    {
    }
}
