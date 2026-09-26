<?php

#[\Testo\Codecov\Covers(\Testo\Pipeline\Middleware\FileLocatorInterceptor::class)]
class BlankLinesTest
{
    public function first(): void
    {
    }

    public function second(): void
    {
    }
}
-----
<?php

class BlankLinesTest
{
    public function first(): void
    {
    }

    public function second(): void
    {
    }
}
