<?php

trait MixedTraitCalls
{
    protected function check($value): void
    {
        $this->assertDatabaseReady($value);
        $this->ASSERTSAME(1, $value);
    }
}
-----
<?php

trait MixedTraitCalls
{
    protected function check($value): void
    {
        $this->assertDatabaseReady($value);
        \Testo\Assert::same($value, 1);
    }
}
