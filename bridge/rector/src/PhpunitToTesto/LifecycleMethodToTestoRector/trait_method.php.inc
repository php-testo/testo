<?php

trait DatabaseAware
{
    protected function setUp(): void
    {
    }
}
-----
<?php

trait DatabaseAware
{
    #[\Testo\Lifecycle\BeforeTest]
    protected function setUp(): void
    {
    }
}
