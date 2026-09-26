<?php

class BlankLinesTest
{
    #[\Testo\Assert\ExpectException(\InvalidArgumentException::class)]
    public function rejectsBadInput(): void
    {
        $input = 'bad';

        new Thing($input);
    }
}
-----
<?php

class BlankLinesTest
{
    public function rejectsBadInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $input = 'bad';

        new Thing($input);
    }
}
