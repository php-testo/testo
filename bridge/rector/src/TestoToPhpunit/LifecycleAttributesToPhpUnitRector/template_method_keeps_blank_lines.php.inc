<?php

class BlankLinesTest
{
    private int $value = 0;

    #[\Testo\Lifecycle\BeforeTest]
    public function setUp(): void
    {
        $this->value = 1;

        $this->value++;
    }
}
-----
<?php

class BlankLinesTest
{
    private int $value = 0;

    public function setUp(): void
    {
        $this->value = 1;

        $this->value++;
    }
}
