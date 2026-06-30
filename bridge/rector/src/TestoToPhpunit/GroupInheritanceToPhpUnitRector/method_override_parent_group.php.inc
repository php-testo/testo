<?php

abstract class MethodBase
{
    #[\Testo\Filter\Group('proto')]
    public function run(): void
    {
    }
}

final class MethodLeafTest extends MethodBase
{
    public function run(): void
    {
    }
}
-----
<?php

abstract class MethodBase
{
    #[\Testo\Filter\Group('proto')]
    public function run(): void
    {
    }
}

final class MethodLeafTest extends MethodBase
{
    #[\PHPUnit\Framework\Attributes\Group('proto')]
    public function run(): void
    {
    }
}
