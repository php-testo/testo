<?php

final class OwnOverrideTest extends \PHPUnit\Framework\TestCase
{
    public function expectException($exception): void
    {
        parent::expectException($exception);
    }
}
-----
<?php

final class OwnOverrideTest
{
    public function expectException($exception): void
    {
    }
}
